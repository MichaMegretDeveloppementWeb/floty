# Stratégie de suppression

> **Version** : 1.0
> **Date** : 23 avril 2026
> **Objet** : modèle de suppression à deux niveaux (logique par défaut, physique sur demande explicite) et son UX associée.

---

## Contexte

Un modèle fiscal est une structure de données **persistante et référencée** : une attribution de 2024 peut être consultée en 2027 via un PDF généré, une règle obsolète en 2025 peut rester référencée par des snapshots de 2024. Une stratégie de suppression trop permissive détruit des références, casse l'historique et dégrade la confiance dans l'outil.

Inversement, une stratégie trop restrictive (refus total de supprimer) frustre l'utilisateur lors des cas d'erreur réels : saisie test, doublon créé par mégarde, véhicule erroné ajouté.

La solution retenue est un **modèle à deux niveaux** : suppression logique (soft delete) par défaut, avec possibilité de suppression physique **sur action explicite délibérée de l'utilisateur**, gardée par un modal à deux niveaux.

---

## 1. Soft delete (suppression logique) — comportement par défaut

### Principe

Toutes les entités métier principales portent un champ `deleted_at TIMESTAMPTZ NULL`. Une suppression utilisateur standard positionne `deleted_at = NOW()` sans toucher aux données ni aux références.

### Entités concernées

Possèdent `deleted_at` :

- `users`
- `vehicles`
- `companies`
- `drivers`
- `assignments`
- `unavailabilities`

**Ne possèdent pas `deleted_at`** (immuables ou d'index) :

- `vehicle_fiscal_characteristics` — historisation : on ne supprime pas une version passée, on la ferme par `effective_to`.
- `fiscal_rules` — journal des règles, désactivation possible via `is_active = false`.
- `declarations` — données fiscales persistantes, pas de suppression UI.
- `declaration_pdfs` — immuables (ADR-0003).

### Effet sur les lectures

- **Vues standard** : filtrent automatiquement `WHERE deleted_at IS NULL` (comportement natif Laravel avec trait `SoftDeletes`).
- **Historique fiscal** (PDF, déclarations, snapshots) : ignore `deleted_at` — les références restent visibles.
- **Restauration** : un bouton « Restaurer » dans l'historique des éléments supprimés (consultable depuis une page dédiée) permet de remettre `deleted_at = NULL`.

### Effet sur les contraintes

Les contraintes d'unicité filtrent sur `WHERE deleted_at IS NULL` — on peut donc recréer une entrée avec la même valeur unique après suppression logique. Exemple :

```sql
UNIQUE (immatriculation) WHERE deleted_at IS NULL
```

Un véhicule `AB-123-CD` supprimé logiquement n'empêche pas la re-saisie d'un nouveau véhicule `AB-123-CD` (cas rare mais légitime : véhicule revendu puis réintégré à la flotte avec nouveau contrat).

### Effet sur les clés étrangères

Les FK utilisent toujours la ligne par ID, **indépendamment du `deleted_at`**. Une attribution soft-deleted garde son `vehicle_id` valide et référencé.

---

## 2. Suppression physique (hard delete) — par action délibérée

### Principe

L'utilisateur peut, **en cas d'erreur réelle (saisie test, doublon)**, supprimer physiquement une ligne. Cette opération déclenche un `DELETE FROM ...` et n'est pas restaurable.

### UX du modal — garde à deux niveaux

Quand l'utilisateur clique sur « Supprimer » depuis une fiche ou une liste :

1. **Modal de confirmation** s'ouvre.
2. **Niveau 1 — suppression logique (par défaut)** :
   - Titre : « Supprimer cet élément ? »
   - Message : « Cet élément sera masqué de l'application mais conservé en historique. Vous pourrez le restaurer ultérieurement. »
   - Bouton principal : **« Confirmer la suppression »** (couleur neutre / orange)
3. **Niveau 2 — option suppression définitive** :
   - Une case à cocher, **décochée par défaut**, isolée visuellement dans un encart rouge :
     > ☐ **Supprimer définitivement de la base de données** (action irréversible)
   - Si cochée, l'encart affiche :
     > ⚠️ **Attention** : cette suppression est irréversible. Les données et les références liées (ex. attributions passées, historique fiscal) vont être évaluées pour vérifier la faisabilité de la suppression. Si des références protégées empêchent la suppression, l'opération sera bloquée.
   - Bouton principal bascule visuellement (texte « Supprimer définitivement », couleur rouge prononcée).
4. Validation : l'utilisateur doit explicitement cocher puis cliquer. Pas d'accident possible par simple double-clic.

### Comportement back selon la case cochée

| État | Action SQL | Restauration possible ? |
|---|---|---|
| Case décochée (défaut) | `UPDATE ... SET deleted_at = NOW()` | Oui, via « éléments supprimés » |
| Case cochée | `DELETE FROM ...` | Non |

### Contraintes protégeant la suppression physique

Les FK en `ON DELETE RESTRICT` bloquent automatiquement la suppression physique d'une entité référencée. Le back renvoie un message structuré que le front affiche dans le modal :

> **Suppression définitive impossible**
>
> Cet élément est référencé par :
> - 47 attributions en 2024
> - 3 déclarations fiscales (dont 2 avec PDF générés)
>
> Vous pouvez utiliser la suppression logique (décochez l'option ci-dessus) pour masquer l'élément sans toucher aux références.

L'utilisateur peut alors :
- Décocher la case et confirmer (soft delete classique)
- Annuler l'opération
- Supprimer manuellement les dépendances (attributions, conducteurs…) puis réessayer — s'il y tient vraiment

### Cascade évaluée, pas automatique

**Aucun `ON DELETE CASCADE`** dans le schéma V1. Motifs :

- Un cascade destructif partant d'une entreprise supprimerait en chaîne ses conducteurs, ses attributions, ses déclarations, ses PDF — et donc détruirait l'historique fiscal **sans que l'utilisateur ne s'en rende compte depuis le modal**.
- `RESTRICT` force un message clair : on ne supprime pas ce qui est référencé, point.

Les cascades logiques (soft delete en chaîne) sont, elles, applicatives et non automatiques : la suppression logique d'une entreprise n'entraîne pas la suppression logique de ses conducteurs ou attributions. L'entreprise disparaît des vues standard, les attributions la référençant restent en base, l'historique fiscal reste exploitable.

---

## 3. Cas particuliers

### 3.1 `users` — l'utilisateur qui se supprime lui-même

- **Soft delete** : bloqué si c'est le seul compte actif. Vérification applicative avant `UPDATE`.
- **Hard delete** : bloqué symétriquement. De plus, un `user` référencé par `declaration_pdfs.generated_by` ou `declarations.status_changed_by` via `ON DELETE SET NULL` peut être supprimé, le champ devient NULL dans les snapshots. Ce comportement est acceptable : les snapshots JSON conservent déjà les informations utiles, la FK n'est qu'un lien pratique.

### 3.2 `vehicles` avec PDF générés dans le passé

- Hard delete bloqué par :
  - `vehicle_fiscal_characteristics.vehicle_id` ON DELETE RESTRICT
  - `attributions.vehicle_id` ON DELETE RESTRICT
  - `indisponibilites.vehicle_id` ON DELETE RESTRICT
- Seul chemin : soft delete.
- Le véhicule reste référencé par les snapshots PDF (via IDs et caractéristiques embarquées dans le JSON). Pas d'impact sur l'immutabilité des PDF.

### 3.3 `assignments` passées déjà incluses dans un PDF

- Soft delete autorisé **mais** déclenche l'invalidation de la déclaration concernée (ADR-0004 : `is_invalidated = true`, `invalidation_reason = 'attribution_modified'`).
- Hard delete : techniquement possible (pas de FK bloquante côté PDF car le snapshot est découplé), **mais** déclenche également l'invalidation de la déclaration concernée.
- Dans les deux cas, le PDF existant reste immuable et consultable en l'état. L'utilisateur voit le badge d'alerte et décide.

### 3.4 `unavailabilities` sans date de fin

Une indisponibilité en cours (`end_date IS NULL`) est modifiable. La « suppression » d'une indisponibilité fermée correspond au même comportement que les autres entités.

### 3.5 `vehicle_fiscal_characteristics` — pas de suppression directe

Une version de caractéristique fiscale ne se supprime pas. Les opérations disponibles sont :

- **Fermer une version** : `UPDATE ... SET effective_to = X WHERE effective_to IS NULL`
- **Corriger une version (erreur de saisie)** : `UPDATE ...` sur la ligne existante, sans créer de nouvelle version
- **Rouvrir une version fermée par erreur** : remettre `effective_to = NULL`, applicatif uniquement, pas dans l'UI V1

En cas d'erreur de création (« j'ai créé une nouvelle version alors que c'était une simple correction ») : support manuel prestataire, hors UI.

### 3.6 `fiscal_rules` — pas de suppression du tout

Les règles sont seeders-only (ADR-0002). Pour « supprimer » :
- Désactiver : `is_active = false` via seeder
- Retirer complètement : impossible tant que des snapshots historiques la référencent

---

## 4. Présentation UI des éléments supprimés

Page dédiée (à positionner en V1) : **« Corbeille »** accessible depuis Paramètres.

- Liste les éléments soft-deleted des 6 entités concernées.
- Pour chaque ligne : intitulé, type, date de suppression, action « Restaurer », action « Supprimer définitivement » (soumise au même modal que ci-dessus).
- Filtrage par type et par période.

En V1, cette page est **basique** : tableau plat, tri par date. Les évolutions UX (filtres avancés, bulk restore, rétention automatique après N mois) sont V2+.

---

## 5. Migration et seeders

Les seeders et les scripts de seed de démonstration utilisent directement `DELETE` ou `TRUNCATE` (hors scope UI). Aucun impact sur la stratégie utilisateur.

Les migrations Laravel implémentent :
- `$table->softDeletes();` sur les 6 tables concernées (ajoute `deleted_at` nullable).
- Les index `UNIQUE ... WHERE deleted_at IS NULL` sont créés via `DB::statement` (Laravel schema builder ne supporte pas nativement les index partiels sur tous les SGBD).

---

## Historique du document

| Version | Date | Auteur | Modifications |
|---|---|---|---|
| 1.0 | 23/04/2026 | Micha MEGRET | Rédaction initiale — modèle soft/hard delete, modal à deux niveaux (option suppression physique désactivée par défaut), règles RESTRICT (pas de cascade), cas particuliers par entité. |
