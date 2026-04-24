# ADR-0009 — Versioning des règles fiscales

> **Statut** : Acceptée
> **Date** : 24 avril 2026
> **Auteur** : Micha MEGRET (prestataire)

---

## Contexte

Les ADRs 0002 (règles non éditables en V1), 0003 (PDF et snapshots immuables) et 0006 (architecture du moteur de règles) supposent implicitement que chaque règle fiscale est identifiée de manière stable et comparable dans le temps. Trois besoins concrets en découlent :

1. Un snapshot de déclaration (ADR-0003) doit mémoriser **exactement quelle version** de chaque règle a été appliquée au moment de la génération, pour rejouer un calcul 10 ans plus tard.
2. Le moteur de règles (ADR-0006) doit, au démarrage, refuser de tourner si deux règles publient la même identité « type de règle × année », ou si une règle critique est manquante.
3. L'invalidation par marquage (ADR-0004) se base sur un hash du snapshot qui inclut les identités de règles ; toute modification non-maîtrisée du schéma d'identité casserait la compatibilité avec l'historique.

La convention d'identité a été laissée implicite jusqu'ici (`R-2024-001` à `R-2024-024` dans `taxes-rules/2024.md`). Cet ADR la formalise.

---

## Décision

### Format canonique d'identité d'une règle

Chaque règle fiscale publiée dans `taxes-rules/{year}.md` porte un identifiant composite de forme :

```
R-{fiscalYear}-{nnn}
```

- `fiscalYear` : année fiscale à laquelle la règle s'applique (4 chiffres, ex. `2024`).
- `nnn` : numéro séquentiel à 3 chiffres, attribué dans l'ordre de première publication dans `taxes-rules/{year}.md`, jamais réattribué.

Exemple : `R-2024-010` = barème WLTP 2024.

### Immuabilité post-seed

Une fois qu'une règle est **seed-able** (fichier `RuleSeeder` committé, première phase de prod atteinte), son identifiant est **gelé à vie** :

- Ni le numéro, ni l'année ne sont jamais modifiés.
- Si un texte BOFiP modifie le comportement d'une règle existante, on publie une **nouvelle règle** (`R-2024-025` par ex.) qui supplante l'ancienne, et on marque l'ancienne `isActive = false` (mais on la conserve pour rejouer les calculs antérieurs).
- Une correction d'erreur de transcription (ex. P0.1 du rapport-001) est autorisée **tant que le seeder n'a pas été déployé** en prod sur la première année. Après déploiement, toute correction passe par une nouvelle règle datée.

### Version interne d'une règle — non-utilisée en V1

Le besoin d'un `rule.version` incrémenté à chaque révision a été évalué et **écarté pour V1** : l'immuabilité + publication d'une nouvelle règle couvre tous les cas. Une règle Floty n'a donc **pas de version numérique** — son identité est le tuple `(id, isActive, publishedAt)`.

### Schéma minimal d'une règle en base (ou en code constantes)

Quelle que soit l'option tranchée pour le stockage des barèmes (PHP code vs BDD — cf. P0.10 du rapport-001), chaque règle expose au minimum :

| Champ | Type | Rôle |
|---|---|---|
| `id` | `string` | `R-{year}-{nnn}` (cf. format canonique) |
| `fiscalYear` | `int` | Année fiscale |
| `ruleType` | `enum RuleType` | Voir ADR-0006 (Tariff / Exemption / ScaleSelection…) |
| `isActive` | `bool` | Permet de retirer une règle supplantée sans casser les snapshots historiques |
| `publishedAt` | `datetime` | Date de première publication (= date du commit qui l'a introduite ; informatif) |
| `description` | `string` | Libellé fonctionnel |

### Référencement dans un snapshot de déclaration

Le snapshot JSON (ADR-0003, format détaillé dans `docs/declaration-snapshot-format.md`) contient, pour chaque véhicule et chaque ligne de calcul :

```json
{
  "appliedRules": [
    { "id": "R-2024-005", "outcome": "WLTP" },
    { "id": "R-2024-010", "computedAmount": 144.64 }
  ]
}
```

Les règles sont désignées **par leur id uniquement**. La sémantique complète est reconstituée à partir du catalogue figé de l'année concernée. Cela garantit que rejouer une déclaration 2024 depuis un snapshot 2024 en 2030 donnera exactement le même résultat, à condition que le catalogue `taxes-rules/2024.md` soit resté figé.

---

## Alternatives écartées

1. **Versioning SemVer par règle** (`R-2024-010@1.0.0`) — complexifie la table, invite à « bumper » plutôt que publier une nouvelle règle, et crée un gradient de variantes qui complique la reproductibilité. Immuabilité + supplantation est plus clair.
2. **Hash de contenu de la règle comme id** — stable vis-à-vis du contenu mais illisible humainement dans les logs et les snapshots. Refusé.
3. **Id numérique simple croissant** (`1, 2, 3, …`) — perte de l'information d'année fiscale dans l'id. Refusé.

---

## Conséquences

- Le seeder initial des règles 2024 fixe définitivement le format `R-2024-xxx` pour l'ensemble du catalogue.
- Un PR qui modifie une règle déjà seedée en prod doit systématiquement publier une nouvelle règle + désactiver l'ancienne ; un CI check « pas de modification d'une règle dont `publishedAt` est antérieur à la date de prod » est à prévoir en phase 13.
- La documentation `taxes-rules/{year}.md` conserve l'historique des corrections intra-cycle (avant prod) en section « Révisions » au fil des règles concernées, tel que pratiqué par exemple sur R-2024-010 (P0.1 du rapport-001).

---

## Références

- ADR-0002 (règles non éditables en V1)
- ADR-0003 (PDF et snapshots immuables)
- ADR-0006 (architecture du moteur de règles)
- `taxes-rules/2024.md` (catalogue R-2024-001 à R-2024-024)
- `rapport-001.md` P1.1 (justification de cet ADR)
