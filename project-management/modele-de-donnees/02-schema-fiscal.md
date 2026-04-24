# Schéma de données — Entités fiscales

> **Version** : 1.1
> **Date** : 24 avril 2026
> **Scope** : règles fiscales (métadonnées pointant vers le code), déclarations, PDF générés avec snapshots immuables, invalidation.
> **SGBD V1** : MySQL 8 (Hostinger Business). Cf. ADR-0008.

Les ADR de référence pour ce dossier sont **ADR-0001** (fiscalité comme donnée), **ADR-0002** (règles non éditables via UI), **ADR-0003** (PDF + snapshots immuables), **ADR-0004** (invalidation par marquage), **ADR-0005** (calcul jour par jour), **ADR-0006** (7 décisions moteur), **ADR-0008** (stack technique V1 MySQL).

---

## 0. Adaptation MySQL 8 — implémentation V1

Les conventions générales d'adaptation MySQL sont documentées dans `01-schema-metier.md` § 0 (mapping des types, encoding, foreign keys, etc.). **Cette section liste uniquement les particularités spécifiques aux entités fiscales.**

### 0.1 Champs `JSONB` → `JSON`

Le schéma initial utilise `JSONB` pour plusieurs colonnes :

| Table | Colonne | Usage | Implémentation MySQL 8 |
|---|---|---|---|
| `fiscal_rules` | `taxes_concerned` | Array de strings (`["co2"]`, `["pollutants"]`, ou les deux) | `JSON` |
| `fiscal_rules` | `vehicle_characteristics_consumed` | Array de strings | `JSON` |
| `fiscal_rules` | `vehicle_characteristics_produced` | Array de strings, optionnel | `JSON` (nullable) |
| `fiscal_rules` | `legal_basis` | Array d'objets structurés (article CIBS, BOFiP) | `JSON` |
| `declaration_pdfs` | `snapshot_json` | Snapshot complet du calcul (cf. § 3.5 du présent document) | `JSON` |

**Pourquoi MySQL `JSON` suffit** :

- Aucune des colonnes JSON n'est **requêtée par clé** (filtrage `WHERE json_field->>'key' = ...`). Les JSON Floty servent à la **sérialisation/désérialisation pure**, pas à l'interrogation indexée.
- Le `snapshot_json` est utilisé pour **calculer un hash SHA-256** (cf. ADR-0004) puis comparé hash à hash. Aucune lecture interne n'est nécessaire.
- `MySQL 8 JSON` stocke en binaire interne avec un parsing optimisé : performances quasi équivalentes à `JSONB` pour les opérations de lecture complète.

**Conséquence pratique** : aucun changement applicatif. Les modèles Eloquent qui castent en `array` ou `object` (`'casts' => ['snapshot_json' => 'array']`) fonctionnent identiquement avec MySQL `JSON` ou PostgreSQL `JSONB`.

### 0.2 Hash SHA-256 — type `CHAR(64)`

Identique en MySQL et PostgreSQL. Aucune adaptation nécessaire. La colonne `pdf_sha256` et `snapshot_sha256` sont des `CHAR(64)` qui stockent le hash hexadécimal.

### 0.3 Pas de soft delete sur les tables fiscales — confirmation

Les 3 tables (`fiscal_rules`, `declarations`, `declaration_pdfs`) **n'ont pas** de colonne `deleted_at` (cf. justifications dans le schéma agnostique : table d'index, données fiscales persistantes, immuabilité PDF). MySQL n'introduit aucune complication ici.

### 0.4 Numérotation séquentielle `declaration_pdfs.version_number`

`declaration_pdfs.version_number` (séquentiel par déclaration) est calculé applicativement (`$declaration->pdfs()->count() + 1`) **dans une transaction** pour garantir l'unicité (sinon race condition si deux générations PDF concurrentes). Cf. `architecture-solid.md` § 4 GenerateDeclarationPdfAction.

L'`UNIQUE (declaration_id, version_number)` au niveau index garantit que même en cas de bug applicatif, MySQL refuse les doublons.

> **Note ADR-0009** : `fiscal_rules` **n'a pas** de colonne `version_internal`. Si une règle présente une erreur, on corrige directement son code (classe PHP) — le `rule_code` en base reste stable, le comportement change pour tous les calculs futurs. L'invalidation par marquage (ADR-0004) détecte la divergence via le hash du snapshot. Tracer les corrections se fait via `git log` et la section « Révisions » des fichiers `taxes-rules/{year}.md`.

### 0.5 Filesystem PDF — chemin relatif

Le `pdf_path` est un `VARCHAR(500)` qui stocke le chemin relatif dans le disque Laravel (ex: `declarations/2024/47/v3-1714000000.pdf`). Aucune particularité MySQL.

> **Important** : la cohérence entre la BDD (entrée `declaration_pdfs`) et le filesystem (présence du fichier) doit être garantie par la **transaction Laravel** dans `GenerateDeclarationPdfAction` :
>
> 1. Calcul + snapshot.
> 2. Écriture filesystem.
> 3. INSERT BDD avec le chemin.
>
> Si l'INSERT BDD échoue après l'écriture filesystem, le fichier devient orphelin (à nettoyer périodiquement par un job cron). C'est une dette technique mineure documentée. Pas de mécanisme XA / 2PC en V1 (MySQL ne le supporte pas trivialement et ce serait du sur-engineering).

### 0.6 Limites héritées

Les autres limites MySQL (pas d'`EXCLUDE`, pas d'index partiels) **n'affectent pas** les tables fiscales :

- Aucune colonne fiscale n'a besoin d'index partiel (les `is_invalidated`, `is_active`, `status` sont des champs simples qu'on indexe entièrement).
- Aucune table fiscale n'a de période avec contrainte d'absence de chevauchement (les `fiscal_rules.applicability_period_start/end` sont indépendantes par règle, sans contrainte de continuité).

**Conclusion : les adaptations MySQL pour les entités fiscales sont minimales** (juste les casts JSON Eloquent, déjà natifs).

---

## 1. `fiscal_rules` — Métadonnées des règles fiscales

Cette table est **l'index consultable** des règles. Elle ne porte pas la logique (cf. ADR-0006 § 3 : logique en code, métadonnées en base).

Alimentée exclusivement par seeders (ADR-0002). Une ligne par (règle × année fiscale). Le code source correspondant vit dans `rules/{année}/{catégorie}/{nom}.php` (arborescence à acter étape 6).

> **Rappel implémentation MySQL 8** : les types `JSONB` et `TIMESTAMPTZ` du tableau ci-dessous sont à mapper en `JSON` et `TIMESTAMP` (UTC) — cf. § 0.1.

| Colonne | Type | Nullable | Défaut | Commentaire |
|---|---|---|---|---|
| `id` | BIGINT | NON | auto | PK technique |
| `rule_code` | VARCHAR(20) | NON | — | Identifiant métier : `R-2024-001` … `R-2024-024` |
| `name` | VARCHAR(255) | NON | — | Titre humain, ex. « Exonération LCD avec cumul par couple » |
| `description` | TEXT | NON | — | Description française, affichée sur la page « Règles de calcul » |
| `fiscal_year` | SMALLINT | NON | — | Année civile d'applicabilité principale (2024, 2025, …) |
| `rule_type` | VARCHAR(20) | NON | — | Énum (`RuleType`) : `classification`, `pricing`, `exemption`, `abatement`, `transversal` (cf. ADR-0006 § 1) |
| `taxes_concerned` | JSONB | NON | — | Array (`TaxType`) : `["co2"]`, `["pollutants"]`, ou `["co2", "pollutants"]` |
| `applicability_start` | DATE | NON | — | Début période d'applicabilité (généralement 01/01 de l'année fiscale) |
| `applicability_end` | DATE | OUI | NULL | Fin ; NULL = toujours applicable |
| `vehicle_characteristics_consumed` | JSONB | NON | `'[]'` | Array de strings : caractéristiques véhicule lues (ex. `["source_energie", "co2_wltp"]`) |
| `vehicle_characteristics_produced` | JSONB | OUI | NULL | Array de strings : caractéristiques calculées/modifiées (optionnel) |
| `legal_basis` | JSONB | NON | — | Array d'objets : `[{"type": "CIBS", "article": "L. 421-129", "url": "..."}, {"type": "BOFIP", "reference": "BOI-AIS-MOB-10-30-10", "paragraph": "§ 180"}]` |
| `code_reference` | VARCHAR(500) | NON | — | Chemin dans le repo : `rules/2024/exonerations/lcd_cumul_couple.php` |
| `display_order` | SMALLINT | NON | — | Ordre d'affichage dans la page de consultation |
| `is_active` | BOOLEAN | NON | `true` | Permet de désactiver une règle sans la supprimer (ex. exonération que le client ne veut plus appliquer) |
| `created_at` | TIMESTAMPTZ | NON | NOW() | |
| `updated_at` | TIMESTAMPTZ | NON | NOW() | |

**Pas de colonne de version** (cf. ADR-0009) : les corrections de règles se font directement dans le code PHP, le `rule_code` en base reste stable. L'historique des corrections vit dans `git log` et dans la section « Révisions » des fichiers `taxes-rules/{year}.md`.

**Index** :
- `UNIQUE (rule_code, fiscal_year)` — un rule_code par année, une seule fois
- `INDEX (fiscal_year, display_order)` — consultation par année
- `INDEX (fiscal_year, rule_type)` — orchestration pipeline
- `INDEX (is_active, fiscal_year)` — règles actives d'une année

**Invariants** :
- `CHECK (applicability_end IS NULL OR applicability_start <= applicability_end)`
- `CHECK (rule_type IN ('classification', 'pricing', 'exemption', 'abatement', 'transversal'))`
- Au démarrage de l'application, le moteur vérifie que pour chaque règle, chaque `vehicle_characteristics_consumed` est soit présent dans le schéma véhicule, soit produit par une règle précédente du pipeline (ADR-0006 § 2).

**Pas de suppression** : cette table est un journal. Les règles obsolètes passent `is_active = false` mais restent référencées par les snapshots historiques. Aucun `deleted_at`.

---

## 2. `declarations` — Déclaration fiscale par (entreprise × année)

Une déclaration = une obligation fiscale d'une entreprise utilisatrice pour une année civile donnée. Porte un **statut** (`draft` / `verified` / `generated` / `sent`, libellés UI : Brouillon / Vérifiée / Générée / Envoyée) et un **flag d'invalidation** (ADR-0004).

> **Rappel implémentation MySQL 8** : les types `TIMESTAMPTZ` du tableau ci-dessous sont à mapper en `TIMESTAMP` (UTC) — cf. § 0.1.

| Colonne | Type | Nullable | Défaut | Commentaire |
|---|---|---|---|---|
| `id` | BIGINT | NON | auto | PK |
| `company_id` | BIGINT | NON | — | FK → `entreprises_utilisatrices.id` |
| `fiscal_year` | SMALLINT | NON | — | Année civile |
| `status` | VARCHAR(20) | NON | `'draft'` | Énum (`DeclarationStatus`) : `draft`, `verified`, `generated`, `sent` |
| `status_changed_at` | TIMESTAMPTZ | NON | NOW() | Date du dernier changement de statut |
| `status_changed_by` | BIGINT | OUI | NULL | FK → `users.id` (qui a changé le statut ; NULL pour brouillon auto-créé) |
| `total_co2_tax` | INTEGER | OUI | NULL | Montant total CO₂ dû, en euros. Rempli lors du calcul, NULL tant que non calculé. |
| `total_pollutant_tax` | INTEGER | OUI | NULL | Idem pour la taxe polluants |
| `total_tax_all` | INTEGER | OUI | NULL | Somme CO₂ + polluants, pour affichage rapide |
| `last_calculated_at` | TIMESTAMPTZ | OUI | NULL | Timestamp du dernier calcul fiscal effectif |
| `is_invalidated` | BOOLEAN | NON | `false` | Drapeau invalidation (ADR-0004) |
| `invalidated_at` | TIMESTAMPTZ | OUI | NULL | Date de l'invalidation (détection automatique) |
| `invalidation_reason` | VARCHAR(50) | OUI | NULL | Énum (`InvalidationReason`) : `attribution_modified`, `vehicle_characteristics_changed`, `unavailability_changed`, `rule_version_changed`, `other` |
| `notes` | TEXT | OUI | NULL | Notes libres (ex. « Envoyée le 3 mai 2025 après vérification par EC ») |
| `created_at` | TIMESTAMPTZ | NON | NOW() | |
| `updated_at` | TIMESTAMPTZ | NON | NOW() | |

**Index** :
- `UNIQUE (entreprise_id, fiscal_year)` — une seule déclaration par couple entreprise × année
- `INDEX (fiscal_year, status)` — page « Déclarations » filtrée par année
- `INDEX (is_invalidated, fiscal_year)` — badge d'alerte

**Invariants** :
- `CHECK (status IN ('draft', 'verified', 'generated', 'sent'))`
- `CHECK (NOT is_invalidated OR invalidated_at IS NOT NULL)` — si invalidée, doit avoir une date
- `FOREIGN KEY (entreprise_id) REFERENCES entreprises_utilisatrices(id) ON DELETE RESTRICT`
- `FOREIGN KEY (status_changed_by) REFERENCES users(id) ON DELETE SET NULL`

**Pas de soft delete** : une déclaration est une donnée fiscale persistante. La suppression d'une déclaration n'est pas une opération utilisateur.

**Transitions d'état** (hors scope strict du modèle mais utile) :
- `draft → verified` : l'utilisateur valide le calcul
- `verified → generated` : un PDF est produit (ADR-0003), crée une ligne dans `declaration_pdfs`
- `generated → sent` : l'utilisateur marque comme transmise à l'administration
- `* → draft` : retour arrière possible, ne supprime pas les PDF déjà générés (ADR-0003)
- Invalidation : orthogonale au statut (peut être invalidée à n'importe quel statut sans transition forcée)

---

## 3. `declaration_pdfs` — Historique des PDF générés

Conformément à ADR-0003, **tous les PDF générés sont conservés**, aucun n'est supprimé automatiquement. Chaque génération crée une ligne ici + un fichier sur le filesystem.

Le binaire PDF est stocké en **filesystem Laravel** (décision Q4 actée), le chemin relatif est en base.

> **Rappel implémentation MySQL 8** : les types `TIMESTAMPTZ` et `JSONB` du tableau ci-dessous sont à mapper en `TIMESTAMP` (UTC) et `JSON` — cf. § 0.1.

| Colonne | Type | Nullable | Défaut | Commentaire |
|---|---|---|---|---|
| `id` | BIGINT | NON | auto | PK |
| `declaration_id` | BIGINT | NON | — | FK → `declarations.id` |
| `pdf_path` | VARCHAR(500) | NON | — | Chemin relatif dans le disque Laravel (ex. `declarations/2024/47/rapport-acme-2024-v2.pdf`). Convention : arborescence par année + id déclaration |
| `pdf_filename` | VARCHAR(255) | NON | — | Nom de fichier humain, ex. `rapport_fiscal_ACME_2024.pdf` (pour téléchargement) |
| `pdf_size_bytes` | BIGINT | NON | — | Taille en octets (pour vérification d'intégrité basique) |
| `pdf_sha256` | CHAR(64) | NON | — | Hash SHA-256 du binaire PDF, pour vérification d'intégrité |
| `snapshot_json` | JSONB | NON | — | Snapshot immuable des données utilisées pour ce PDF (ADR-0003 § 5 et ADR-0006 § 5) |
| `snapshot_sha256` | CHAR(64) | NON | — | Hash SHA-256 du snapshot_json (canonicalisé) — utilisé pour la détection d'invalidation (ADR-0004) |
| `generated_at` | TIMESTAMPTZ | NON | NOW() | Moment de la génération |
| `generated_by` | BIGINT | OUI | NULL | FK → `users.id` |
| `version_number` | INTEGER | NON | — | Numéro de version de ce PDF pour la déclaration : 1, 2, 3… (incrémenté à chaque régénération) |
| `created_at` | TIMESTAMPTZ | NON | NOW() | |

**Note : pas de `updated_at`, pas de `deleted_at`** — immutabilité stricte (ADR-0003).

**Index** :
- `INDEX (declaration_id, version_number DESC)` — afficher la liste historique, dernier en premier
- `UNIQUE (declaration_id, version_number)` — numérotation séquentielle sans doublon
- `INDEX (generated_at DESC)` — vue chronologique globale
- `INDEX (snapshot_sha256)` — comparaison rapide pour invalidation

**Contraintes** :
- `FOREIGN KEY (declaration_id) REFERENCES declarations(id) ON DELETE RESTRICT` — une déclaration ne peut pas être supprimée tant qu'elle a des PDF générés
- `FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL`

### Structure du `snapshot_json`

Le snapshot capture **tout ce qui a été utilisé pour produire le PDF**. Structure recommandée :

```json
{
  "schema_version": "1.0",
  "generated_at": "2025-04-12T14:30:00Z",
  "declaration": {
    "id": 47,
    "fiscal_year": 2024,
    "entreprise": {
      "id": 12,
      "raison_sociale": "ACME SARL",
      "siren": "123456789",
      "code_court": "AC"
    }
  },
  "rules_applied": [
    {"rule_code": "R-2024-001", "version_internal": 3},
    {"rule_code": "R-2024-021", "version_internal": 7},
    ...
  ],
  "vehicle_characteristics": {
    "42": [
      {"effective_from": "2024-01-01", "effective_to": "2024-03-11", "co2_wltp": 118, ...},
      {"effective_from": "2024-03-12", "effective_to": null, "source_energie": "e85", ...}
    ],
    ...
  },
  "attributions": [
    {"vehicle_id": 42, "date": "2024-01-02"},
    ...
  ],
  "indisponibilites_fiscales": [
    {"vehicle_id": 42, "start_date": "2024-06-01", "end_date": "2024-06-10", "type": "fourriere"}
  ],
  "calculation_results": {
    "par_couple": [
      {
        "vehicle_id": 42,
        "cumul_jours": 28,
        "exoneration_lcd_applicable": true,
        "taxe_co2": 0,
        "taxe_polluants": 0,
        "motif": "Exonération LCD (R-2024-021) : cumul 28 jours < seuil 30 jours"
      },
      ...
    ],
    "total_co2": 1240,
    "total_polluants": 380,
    "total": 1620
  }
}
```

Le **`snapshot_sha256`** est calculé sur ce JSON canonicalisé (clés triées, espaces normalisés). C'est ce hash qui permet la détection d'invalidation (ADR-0004) :

1. À la demande (ou sur trigger), le moteur recalcule le snapshot à partir des données courantes.
2. Compare le `snapshot_sha256` recalculé avec celui stocké dans le dernier `declaration_pdfs` de la déclaration.
3. Si divergence → `declarations.is_invalidated = true`, `invalidation_reason` renseigné, badge affiché dans l'UI.
4. **Jamais de régénération automatique** (ADR-0004) — l'utilisateur décide.

**Filesystem** — arborescence de stockage recommandée :

```
storage/app/declarations/
├── {fiscal_year}/
│   └── {declaration_id}/
│       ├── v1-{timestamp}.pdf
│       ├── v2-{timestamp}.pdf
│       └── ...
```

Avec disque Laravel configuré sur `local` en V1. Migration vers S3 / objet store possible en V3 sans impact applicatif (abstraction Laravel Filesystem).

---

## 4. Relations

```
users                                       fiscal_rules
  │                                          (référencées dans snapshot_json
  │ generated_by                              mais pas de FK stricte —
  │ status_changed_by                         table d'index consultable)
  │
  v
declarations ──1──────────N── declaration_pdfs
   │                           │
   │ entreprise_id             │ snapshot_json référence implicitement :
   │                           │   - vehicle_id + effective_from (historisation)
   v                           │   - attributions du couple × année
entreprises_utilisatrices      │   - rule_code + version_internal
                               │
                               v
                            filesystem (storage/app/declarations/…)
```

**Pas de FK physique** entre `declaration_pdfs.snapshot_json` et les données courantes : le snapshot est délibérément **découplé** pour rester immuable même si les entités référencées sont supprimées ou modifiées.

---

## 5. Cas-limite : suppression d'une entreprise référencée par un PDF

Scénario : l'utilisateur tente de supprimer physiquement une entreprise qui a des déclarations avec PDF générés.

Règles (cf. `03-strategie-suppression.md`) :

1. **Soft delete** : toujours autorisé. Le PDF et sa déclaration restent consultables via l'historique.
2. **Suppression physique** : **bloquée** par la contrainte `ON DELETE RESTRICT` entre `declarations.entreprise_id` et `entreprises_utilisatrices.id`. Modal affiche un message clair : « Cette entreprise a N déclaration(s) et M PDF(s) générés. Suppression physique impossible. Utilisez la désactivation (`is_active = false`) ou la suppression logique (`deleted_at`). »

Cette règle vaut symétriquement pour les véhicules, les conducteurs, et les utilisateurs ayant généré des PDF.

---

## 6. Cas-limite : correction d'une règle fiscale déjà appliquée

Scénario : on découvre qu'une règle (ex. R-2024-021) a un bug de calcul. On **corrige directement** le code PHP de la règle (cf. ADR-0009).

Comportement attendu :

1. Les snapshots PDF existants restent **strictement inchangés** — ils référencent `"ruleCode": "R-2024-021"` et contiennent les montants calculés à l'époque.
2. Au prochain calcul (lecture d'une déclaration déjà générée), le moteur exécute la version corrigée sur les données courantes.
3. Le `snapshot_sha256` recalculé diverge du `snapshot_sha256` stocké → `declarations.is_invalidated = true` avec `invalidation_reason = 'rule_version_changed'`.
4. L'utilisateur voit les badges d'alerte et décide de régénérer un nouveau PDF ou pas (ADR-0004).

**Les PDF anciens restent valides et accessibles en l'état** — ils témoignent de ce qui a été transmis à l'administration à la date de la déclaration, ce qui a une valeur juridique. La régénération produit un nouveau PDF (`version_number` incrémenté) qui coexiste avec les précédents.

La traçabilité de la correction vit dans `git log` de la classe PHP modifiée + dans la section « Révisions » du catalogue `taxes-rules/{year}.md`. Aucune métadonnée SQL n'est nécessaire.

---

## Historique du document

| Version | Date | Auteur | Modifications |
|---|---|---|---|
| 1.2 | 24/04/2026 | Micha MEGRET | Passe d'audit (étape 5.6) — application de la convention E1 (anglais strict pour les enums Floty) : `DeclarationStatus` valeurs `'draft'`/`'verified'`/`'generated'`/`'sent'`, `RuleType` valeurs `'classification'`/`'pricing'`/`'exemption'`/`'abatement'`/`'transversal'`, `TaxType` `'co2'`/`'pollutants'`, `InvalidationReason` toutes en anglais. Transitions d'état mises à jour (`draft → verified → generated → sent`). Ajout de rappels inline « MySQL 8 : `JSONB` → `JSON`, `TIMESTAMPTZ` → `TIMESTAMP` » au-dessus de chaque tableau pour éviter les pièges de lecture. |
| 1.1 | 24/04/2026 | Micha MEGRET | Ajout de la section « 0. Adaptation MySQL 8 — implémentation V1 » suite à ADR-0008. Documente le mapping `JSONB → JSON` (5 colonnes concernées : 4 dans fiscal_rules + snapshot_json), confirme que les autres limites MySQL n'affectent pas les entités fiscales (pas d'`EXCLUDE` ni d'index partiel nécessaire), précise la gestion applicative de `version_internal` et `version_number` avec garantie d'unicité via index UNIQUE, documente la cohérence transactionnelle BDD ↔ filesystem PDF (avec dette technique mineure des fichiers orphelins en cas d'échec). Le schéma original (sections 1-6) reste lisible comme spec agnostique. |
| 1.0 | 23/04/2026 | Micha MEGRET | Rédaction initiale — 3 tables fiscales V1 (fiscal_rules, declarations, declaration_pdfs) avec structure snapshot, hash SHA-256, stratégie filesystem Laravel, cas-limites suppression et invalidation. |
