# ADR-0009 — Identité et correction des règles fiscales

> **Statut** : Acceptée
> **Date** : 24 avril 2026
> **Auteur** : Micha MEGRET (prestataire)
> **Révision 2026-04-24** : suppression de toute notion de versioning numérique.

---

## Contexte

Les ADRs 0002 (règles non éditables en V1), 0003 (PDF et snapshots immuables) et 0006 (architecture du moteur de règles) supposent implicitement que chaque règle fiscale est identifiée de manière stable et que ses corrections suivent un processus clair. Cet ADR formalise les deux points :

1. Le **format d'identité** d'une règle (rule code).
2. La **politique de correction** quand une règle s'avère erronée.

---

## Décision

### Format canonique d'identité

Chaque règle publiée dans `taxes-rules/{year}.md` porte un identifiant composite :

```
R-{fiscalYear}-{nnn}
```

- `fiscalYear` : année fiscale (4 chiffres, ex. `2024`).
- `nnn` : numéro séquentiel à 3 chiffres dans l'ordre de première publication, jamais réattribué.

Exemple : `R-2024-010` = barème WLTP 2024.

L'identifiant **est immuable** : on ne renumérote jamais une règle. C'est la seule contrainte d'immuabilité.

### Pas de versioning numérique des règles

V1 fait le choix délibéré de **ne pas versionner les règles** (ni SemVer, ni `version_internal` incrémenté, ni hash de contenu) :

- Si une règle présente une erreur de calcul, on **corrige directement** le code de la règle. Le `rule_code` reste stable, l'entrée en base reste la même, le code change.
- À partir du commit de correction, **toute exécution future** du moteur applique la version corrigée.
- Les snapshots PDF antérieurs (ADR-0003) **conservent leur valeur juridique** : ils témoignent du calcul transmis à l'administration à la date de la déclaration. Ils ne sont jamais modifiés.
- L'invalidation par marquage (ADR-0004) opère sa magie : si une règle change, le hash recalculé sur les données courantes diverge du hash stocké → la déclaration est marquée `is_invalidated`, badge UI affiché. L'utilisateur décide s'il régénère (et donc remplace la pièce justificative) ou pas.

Toute notion de version est gérée **hors application** :

- L'historique des corrections vit dans `git log` des fichiers `app/Fiscal/Rules/{year}/...` et de `taxes-rules/{year}.md`.
- Les changements significatifs sont documentés dans une section « Révisions » du `taxes-rules/{year}.md` au-dessus de la règle concernée.
- Aucune table SQL `rule_versions`, aucun champ `version_internal`, aucune duplication de classes Rule.

### Désactivation d'une règle

Une règle peut être **désactivée** (`is_active = false`) si elle devient sans objet (ex. exonération métier que le client ne souhaite plus appliquer). C'est une opération rare, prise au cas par cas par seeder. Désactiver n'est pas la même chose qu'amender — c'est retirer la règle du pipeline pour les calculs futurs.

### Schéma minimal d'une règle en base

| Champ | Type | Rôle |
|---|---|---|
| `rule_code` | `string` (PK métier) | `R-{year}-{nnn}` |
| `fiscal_year` | `int` | Année fiscale |
| `rule_type` | `enum RuleType` | Voir ADR-0006 |
| `taxes_concerned` | `JSON` | `["co2"]`, `["pollutants"]`, ou les deux |
| `is_active` | `bool` | Désactivation (rare, métier) |
| `name` | `string` | Libellé court |
| `description` | `text` | Description française |
| `legal_basis` | `JSON` | Articles CIBS / BOFiP référencés |
| `code_reference` | `string` | Chemin vers la classe PHP |
| `display_order` | `smallint` | Ordre d'affichage page consultation |

**Pas** de `version_internal`, **pas** de `published_at` (l'info se lit dans `git log` ou dans le seeder), **pas** de `is_active_history`.

### Référencement dans un snapshot

Les snapshots JSON (ADR-0003) référencent les règles par `rule_code` uniquement :

```json
{
  "appliedRules": [
    { "ruleCode": "R-2024-005", "outcome": "WLTP" },
    { "ruleCode": "R-2024-010", "computedAmount": 144.64 }
  ]
}
```

Pas de version, pas de hash de règle. Le `snapshot_sha256` global capture l'ensemble des données utilisées (et indirectement, par la valeur des `computedAmount`, le comportement de la règle au moment du calcul).

---

## Alternatives écartées

1. **`version_internal` incrémenté à chaque correction** — proposition initiale 02-schema-fiscal.md v1.0. Écartée : duplique l'info disponible dans git, complique la table sans gain pour l'utilisateur final qui ne consulte jamais les versions internes.
2. **Versioning SemVer par règle** (`R-2024-010@1.0.0`) — complexifie l'UI consultation, invite à « bumper » au lieu de simplement corriger. Refusé.
3. **Supplantation par publication d'une nouvelle règle** (`R-2024-025` remplace `R-2024-010` corrigée) — raisonnement défendable mais sur-engineering pour le contexte Floty (règles peu nombreuses, prestataire unique qui maîtrise le catalogue, corrections rares post-prod).
4. **Hash de contenu de la règle comme id** — stable vis-à-vis du contenu mais illisible humainement dans les logs et snapshots. Refusé.

---

## Conséquences

- La table `fiscal_rules` n'a **pas** de colonne `version_internal` (cf. amendement 02-schema-fiscal.md). Si on en avait, l'invalidation par hash deviendrait deux fois moins puissante (les snapshots porteraient la version, mais la version augmenterait à chaque correction et le seul moyen de re-vérifier reste de comparer le hash global, donc autant ne rien stocker).
- Les snapshots JSON portent `ruleCode` seulement — pas de version.
- Une correction de règle déclenche automatiquement l'invalidation de toutes les déclarations dont le hash diffère désormais (ADR-0004). C'est l'effet recherché.
- Le PR qui corrige une règle doit :
  1. Ajouter une entrée dans la section « Révisions » de `taxes-rules/{year}.md` au-dessus de la règle concernée (date, motif, ancienne valeur → nouvelle valeur).
  2. Modifier la classe PHP de la règle.
  3. Modifier les seeders si la `description` ou `legal_basis` changent.
  4. Lancer les golden tests fiscaux pour s'assurer qu'aucun cas attendu n'est cassé.

---

## Références

- ADR-0002 (règles non éditables en V1)
- ADR-0003 (PDF et snapshots immuables)
- ADR-0004 (invalidation par marquage)
- ADR-0006 (architecture du moteur de règles)
- `taxes-rules/2024.md` (catalogue R-2024-001 à R-2024-024)
- `rapport-001.md` P1.1 (déclencheur initial de l'ADR)
- Décision client 2026-04-24 : pas de versioning, correction directe en code (révision majeure de cet ADR)
