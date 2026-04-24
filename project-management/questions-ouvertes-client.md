# Questions ouvertes à trancher avec le client

> **Date de création** : 24 avril 2026 (suite rapport-001 P0.10 + section 12)
> **Statut** : 4 questions bloquantes pour le démarrage de phases spécifiques. Aucune n'empêche la phase 01 (fondations backend).

---

## Q1 — Exercice fiscal cible V1 [PRIORITÉ HAUTE]

### Contexte

Nous sommes en avril 2026. La déclaration fiscale de l'exercice 2024 se fait en janvier 2025 — **déjà passée depuis 15 mois**. La déclaration 2025 se fait en janvier 2026 — passée depuis 3 mois. La prochaine échéance utile sera la déclaration 2026 en janvier 2027.

Le catalogue actuel `taxes-rules/2024.md` couvre uniquement l'exercice 2024. Les dossiers `recherches-fiscales/2025/`, `recherches-fiscales/2026/`, `taxes-rules/2025.md`, `taxes-rules/2026.md` sont **vides ou inexistants**.

### Question

Quel exercice fiscal Floty doit-il couvrir à la livraison V1 ?

### Options

| Option | Implications |
|---|---|
| **A. 2024 seul (rétroactif, pour justifier un contrôle)** | Plan actuel tenable. Utile si le client a un contrôle fiscal en cours ou anticipé. V1 livrable rapidement. |
| **B. 2024 + 2025** | Doubler l'effort de recherche fiscale (~3-4 j) + seeder `FiscalRules2025Seeder`. Le catalogue 2025 peut présenter des modifications (LF 2025). |
| **C. 2024 + 2025 + 2026** | Triplement recherche fiscale. Permet d'utiliser Floty en janvier 2027 pour la déclaration 2026 **opérationnellement**. Recommandé si l'objectif est l'usage en production. |
| **D. 2026 seul (prospectif)** | Plus simple que C mais perd la capacité de rejouer les exercices passés. |

### Recommandation Micha

Option **C** si le budget et le délai le permettent — c'est la seule qui fait de Floty un outil **réellement utilisable** dès sa livraison. Si C est hors budget, option **B** est un compromis acceptable (permet de finaliser la déclaration 2025 si le client l'a en retard, et de préparer 2026).

### Impact sur le plan

- Ajouter `recherches-fiscales/2025/` et `recherches-fiscales/2026/` avec le même protocole que 2024.
- Créer `taxes-rules/2025.md` et `taxes-rules/2026.md`.
- Dupliquer la phase 10 partiellement (seeders par année).
- La sélection d'année (YearSelector cf. tâche 02.18) prend son sens réel.

---

## Q2 — Noms de colonnes BDD et propriétés DTO en français ou anglais strict ? [PRIORITÉ HAUTE]

### Contexte

La convention **E1 « anglais strict total »** a été adoptée pour : noms de classes, de méthodes, de variables PHP/TS, de tables, **et de colonnes SQL**. Seuls sont conservés en FR les libellés UI et les codes administratifs français (VP, VU, CI, BB, CTTE, BE, HB, WLTP, NEDC, PA, SIREN, SIRET, VIN).

Toutefois, les documents `implementation-rules/` et `modele-de-donnees/` contiennent encore des exemples avec des noms de colonnes français (`immatriculation`, `marque`, `modele`, `couleur`…). C'était un reliquat pré-E1 — la convention E1 est documentée dans `conventions-nommage.md` mais l'alignement des exemples n'est pas encore complet (en cours — cf. corrections P0.4 du rapport-001 partiellement appliquées).

### Question

Confirmes-tu la convention **E1 strict anglais** jusque dans les colonnes BDD ?

### Options

| Option | Implications |
|---|---|
| **A. E1 strict — anglais partout** | `vehicles.license_plate`, `vehicles.brand`, `vehicles.model`, `vehicles.color`, `drivers.first_name`… Codes FR préservés (`vehicle_user_type` valeurs `VP`/`VU`…). Alignement idiomatique Laravel/Eloquent. Cohérence avec le reste du code. |
| **B. Hybride — colonnes FR, code EN** | `vehicles.immatriculation`, `vehicles.marque`, etc. Code PHP garde les casts FR. Cohérence métier + lisibilité BDD pour le client si accès direct. Anti-pattern Eloquent (accesseurs à créer). |

### Recommandation Micha

Option **A**. La convention E1 a été choisie en connaissance de cause ; garder la cohérence intégrale évite le coût mental de traduction FR↔EN en permanence. Le client n'ira pas regarder la BDD directement.

### Impact si décision = A

- Compléter les renommages dans `modele-de-donnees/01-schema-metier.md` (exemples SQL).
- Compléter les exemples dans `implementation-rules/gestion-erreurs.md` (FormRequest), `architecture-solid.md` (Eloquent), `tests-frontend.md` (fixtures — déjà corrigé en rapport-001 P0.4).
- Toutes les migrations V1 utilisent des noms de colonnes anglais.

---

## Q3 — Barèmes fiscaux en PHP code ou en BDD ? [PRIORITÉ MOYENNE]

### Contexte

Les règles fiscales comme R-2024-010 (barème WLTP 2024) ont des **tables de tranches chiffrées** (9 tranches × 4 colonnes). Deux options d'implémentation :

### Options

| Option | Implications |
|---|---|
| **A. Constantes PHP en code** | Les tranches sont des `array` dans la classe `Rule` (ex. `WltpTariff2024Rule::TRANCHES`). Avantage : règles non-éditables par design (ADR-0002), versionnées par git, pas de migration pour modifier. Inconvénient : seed fiscal 2024 = code diff, pas un enregistrement en base. |
| **B. Tables dédiées** (`fiscal_scale_co2_2024`, `fiscal_scale_pollutants_2024`…) | Les tranches sont dans des tables lues par les Rules au runtime. Avantage : schéma de données homogène, export/import facile. Inconvénient : mutable par SQL (contredit ADR-0002 « non-éditables »), multiplie les tables d'années. |

### Recommandation Micha

Option **A**. Cohérent avec ADR-0002 (règles non éditables). Moins de migrations. Les barèmes figurent alors dans `app/Fiscal/Rules/{Year}/` avec structure de classes. Un test golden vérifie au démarrage que chaque règle publie bien les valeurs documentées dans `taxes-rules/{year}.md`.

### Impact si décision = A

- Pas de tables `fiscal_scale_*` en V1.
- La phase 10 (moteur fiscal) organise les règles en namespaces par année.
- Chaque Rule expose une méthode `getParameters(): array` qui renvoie les tranches pour intégration dans le snapshot JSON.

---

## Q4 — Format du snapshot JSON à figer [PRIORITÉ MOYENNE]

### Contexte

L'ADR-0003 (PDF snapshots immuables) impose un `snapshot_json` conservé 10 ans pour chaque génération de déclaration. Le format exact n'est pas encore défini. Le plan-implementation phase 11 référence un fichier `docs/declaration-snapshot-format.md` qui **n'existe pas encore**.

### Action

Rédiger `docs/declaration-snapshot-format.md` avant la phase 11.

### Structure proposée (à valider)

```json
{
  "snapshotVersion": "1.0",
  "generatedAt": "2026-05-12T10:34:21+02:00",
  "fiscalYear": 2024,
  "company": {
    "id": 42,
    "legalName": "ACME Industries",
    "siren": "123456789",
    "address": "..."
  },
  "vehicles": [
    {
      "id": 128,
      "licensePlate": "EH-142-AZ",
      "brand": "Peugeot",
      "model": "308",
      "fiscalCharacteristicsSnapshot": { /* toutes les caractéristiques à la date de ref */ },
      "assignmentsCount": 204,
      "daysWithCompany": 156,
      "lcdCumul": 34,
      "appliedRules": [
        { "id": "R-2024-005", "outcome": "WLTP" },
        { "id": "R-2024-010", "computedAmount": 144.64 },
        { "id": "R-2024-021", "applied": false, "reason": "cumul > 30j" }
      ],
      "co2Tax": 144.64,
      "pollutantsTax": 100.00,
      "totalTax": 244.64
    }
  ],
  "totals": {
    "co2Tax": 14864.12,
    "pollutantsTax": 8250.00,
    "totalTax": 23114.12
  },
  "hashAlgorithm": "SHA-256",
  "hash": "abc123..."
}
```

### Question

Es-tu OK avec cette structure ? Points à valider :
- Inclusion d'une snapshot complète des caractéristiques véhicule à la date de référence (vs juste le pointer vers la version historisée) ?
- Granularité des `appliedRules` : id + outcome/amount seulement, ou inclusion de plus de traces (intermédiaires) ?
- Signature cryptographique (hash seul) suffisante, ou HMAC signé avec clé secrète pour prouver l'origine du snapshot ?

### Recommandation Micha

- Snapshot complet des caractéristiques (permet de rejouer sans dépendance à `vehicle_fiscal_characteristics` qui pourrait être supprimée hors période conservation).
- Trace des règles au niveau id + outcome + amount (ne pas stocker les calculs intermédiaires, reconstituables).
- Hash SHA-256 simple en V1, signé HMAC en V2 si contrôle fiscal exige une preuve d'origine plus forte.

---

## Processus de validation

Ces 4 questions sont remontées dans ce fichier pour arbitrage client. Elles peuvent être traitées individuellement (réponse par fichier `prompt.txt` au format : `Q1 : option C` + commentaire). À chaque réponse, le prestataire :

1. Met à jour le ou les ADRs concernés.
2. Ajuste les tâches du plan d'implémentation affectées.
3. Retire la question de ce fichier (conservée en archive git).
