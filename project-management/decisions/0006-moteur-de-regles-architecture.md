# ADR-0006 — Architecture du moteur de règles fiscales

> **Statut** : Acceptée
> **Date** : 23 avril 2026
> **Auteur** : Micha MEGRET (prestataire)

---

## Contexte

Les ADR précédents ont posé les principes fondateurs :

- **ADR-0001** : la fiscalité est traitée comme une donnée, le moteur est générique.
- **ADR-0002** : les règles sont éditées exclusivement par l'équipe technique via seeders.
- **ADR-0003** : les PDF de déclarations et leurs snapshots sont immuables.
- **ADR-0004** : les déclarations invalidées sont marquées, non régénérées automatiquement.
- **ADR-0005** : le calcul est journalier.

Il restait à concevoir l'**architecture concrète** du moteur de règles : quelle est la forme d'une règle ? comment sont-elles invoquées ? où vivent-elles (code, base) ? quels modes d'exécution sont supportés ? comment est construite la page de consultation ?

Cette architecture a été conçue après la phase de recherche fiscale 2024, avec les **24 règles concrètes** du catalogue 2024 en main (cf. `project-management/taxes-rules/2024.md`). Cette antériorité est importante : les choix ont été calibrés sur des règles réelles, pas sur des hypothèses.

## Décision

L'architecture du moteur de règles se décompose en **sept décisions cohérentes**, décrites ci-dessous. Elles doivent être lues comme un ensemble solidaire : chacune conditionne et est conditionnée par les autres.

---

### 1. Anatomie d'une Règle — interface de base + cinq sous-types spécialisés

Chaque règle fiscale implémente une interface de base commune qui porte ses métadonnées :

```
interface FiscalRule {
  id: string                                    // ex: "R-2024-021"
  type: RuleType                                // énumération — cf. sous-types ci-dessous
  taxConcerned: TaxType[]                       // [CO2], [POLLUANTS], ou les deux
  applicabilityPeriod: DateRange                // début → fin de validité
  vehicleCharacteristicsConsumed: string[]      // caractéristiques lues en entrée
  vehicleCharacteristicsProduced?: string[]     // caractéristiques calculées ou modifiées (optionnel)
  legalBasis: LegalReference[]                  // articles CIBS, BOFiP, etc.
  description: string                           // français lisible

  describeForUI(): RuleDescription              // pour la page de consultation
}
```

Au-delà de cette interface commune, chaque règle implémente **l'un** des cinq sous-types fonctionnels suivants, selon ce qu'elle fait sémantiquement :

| Sous-type | Rôle | Exemples dans le catalogue 2024 |
|---|---|---|
| **`ClassificationRule`** | Produit une qualification à partir des caractéristiques véhicule | R-2024-004 (qualification M1/N1), R-2024-005 (détermination barème CO₂), R-2024-006 (bascule PA si CO₂ manquant), R-2024-013 (catégorisation polluants) |
| **`TarificationRule`** | Produit un tarif annuel plein à partir des caractéristiques | R-2024-010/011/012 (barèmes WLTP/NEDC/PA), R-2024-014 (tarif forfaitaire polluants) |
| **`ExonerationRule`** | Vérifie une condition d'exonération, éventuellement avec état (cumul) | R-2024-015 (handicap), R-2024-016 à R-2024-022 (autres exonérations dont LCD à cumul par couple) |
| **`AbatementRule`** | Modifie une caractéristique d'entrée avant tarification | Pas en 2024 — apparaît en 2025 (abattement E85) |
| **`TransversalRule`** | Fournit des valeurs ou opérations transverses (prorata, arrondi, historisation, indisponibilités) | R-2024-001, R-2024-002, R-2024-003, R-2024-007, R-2024-008, R-2024-009 |

**Note importante** : les champs `confidence` et `associatedUncertainty` — qui pourraient sembler utiles — ne figurent **pas** sur l'interface. Ces concepts relèvent exclusivement de la documentation interne (`project-management/recherches-fiscales/`, `project-management/taxes-rules/`), pas de l'application. L'application présente les règles comme autoritaires, sans disclaimer. Toute discussion sur la confiance d'une règle ou sur des incertitudes se fait hors application, par la documentation — cf. ADR-0001 pour le rôle pont de la documentation.

---

### 2. Pipeline d'orchestration — ordre fixe en huit étapes

Pour un contexte donné `(véhicule, entreprise utilisatrice, année fiscale)`, l'orchestrateur exécute le pipeline suivant :

```
1. RÉCUPÉRATION DU CONTEXTE
   ├─ Caractéristiques véhicule à la date de chaque attribution (application de la règle d'historisation)
   └─ Liste des attributions du couple sur l'année civile

2. CLASSIFICATION (ClassificationRule)
   ├─ Qualification du type fiscal (taxable / non taxable / hors périmètre)
   ├─ Détermination du barème CO₂ applicable (WLTP / NEDC / PA)
   ├─ Bascule PA si donnée CO₂ manquante
   └─ Catégorisation polluants (E / 1 / plus polluants)

3. CALCUL DU CUMUL (TransversalRule, état persisté)
   └─ cumul_jours_couple = somme des jours d'attribution — déduction faite des indisponibilités à impact fiscal

4. EXONÉRATIONS (ExonerationRule, dans un ordre défini)
   ├─ Vérification en série des exonérations applicables
   └─ Si une exonération renvoie "totale" → STOP, taxe = 0 (avec motif)

5. ABATTEMENTS (AbatementRule)
   └─ Modification des caractéristiques avant tarification (non utilisé en 2024)

6. TARIFICATION (TarificationRule)
   ├─ Calcul du tarif annuel plein CO₂
   └─ Calcul du tarif annuel plein polluants

7. PRORATA + ARRONDI (TransversalRule)
   ├─ Application du prorata journalier
   └─ Arrondi à l'euro le plus proche

8. SORTIE STRUCTURÉE
   └─ { taxe_co2, taxe_polluants, motif, détails des étapes intermédiaires }
```

Caractéristiques du pipeline :

- **Ordre fixe** en V1 (pas de moteur de dépendances dynamique).
- **Court-circuit sur exonération totale** pour éviter les calculs inutiles.
- **Trace complète** en sortie, pour audit et PDF.

La validation de cohérence du pipeline est effectuée au démarrage de l'application : pour chaque règle, toute caractéristique déclarée comme `vehicleCharacteristicsConsumed` doit être soit présente sur le schéma véhicule, soit produite par une règle s'exécutant avant dans le pipeline.

---

### 3. Stockage — logique en code, métadonnées en base

**Logique en code** :

- Chaque règle est une classe PHP `final readonly`, versionnée dans le repository git.
- Le code contient la logique d'évaluation, les paramètres (tranches, seuils, tarifs), et la méthode `describeForUI()`.
- Organisation par dossier : `app/Fiscal/Year2024/Pricing/R2024_010_WltpProgressive.php`, `app/Fiscal/Year2024/Exemption/R2024_021_LowDayCount.php`, etc.

**Métadonnées en base** (table `fiscal_rules`) :

- `id`, `name`, `description`
- `type`, `tax_concerned`
- `applicability_period_start / end`
- `vehicle_characteristics_consumed`, `vehicle_characteristics_produced` (JSON)
- `legal_basis` (JSON structuré : article CIBS, URL BOFiP, etc.)
- `code_reference` (chemin de la classe dans le repo)
- `display_order`, `is_active`
- `created_at`, `updated_at`

**Les métadonnées ne dupliquent pas la logique**. Elles sont l'index consultable et auditable, pointant vers le code qui porte la logique. Cette répartition respecte l'ADR-0002 (édition exclusive par l'équipe technique via seeders).

---

### 4. Modes d'exécution — trois modes, un seul moteur

Le même moteur (mêmes règles, même pipeline) supporte trois modes d'invocation :

| Mode | Entrée | Sortie | Usage |
|---|---|---|---|
| **Calcul annuel** | Entreprise + année | Taxes dues par couple, total annuel, données pour le PDF | Génération des déclarations fiscales |
| **Simulation** | Couple (véhicule, entreprise) + attribution hypothétique | Impact fiscal estimé, nouveau cumul, bascule éventuelle | Alimentation du compteur LCD temps réel dans l'UI (vue par entreprise) |
| **Génération PDF** | Entreprise + année | PDF + snapshot immuable | Production de la pièce justificative officielle |

**Pourquoi un seul moteur pour les trois modes** : garantir que le compteur affiché à l'utilisateur, le calcul officiel, et le PDF sont **strictement cohérents**. Trois implémentations distinctes produiraient inévitablement des divergences subtiles, et compromettraient la confiance dans l'outil.

---

### 5. Snapshots et audit

Conformément à l'ADR-0003 (PDF immuables) et à l'ADR-0004 (invalidation par marquage) :

- À chaque génération PDF, un **snapshot JSON** est persisté avec le PDF. Il capture :
  - Les règles appliquées (`rule_code` uniquement, cf. ADR-0009)
  - Les attributions du couple
  - Les caractéristiques véhicule à la date du calcul
  - Les données de l'entreprise utilisatrice
  - Les résultats intermédiaires et finaux
- **Pas de versioning des règles** (cf. ADR-0009). Quand une règle est corrigée, le code change, le `rule_code` reste stable, les snapshots historiques restent intacts.
- La détection d'invalidation (ADR-0004) compare un hash du snapshot avec un hash de l'état courant recalculé. Si divergence → marquage.

---

### 6. Page de consultation — lecture seule en V1

Conformément à l'ADR-0002, la page « Fiscalité → Règles de calcul » (cahier des charges § 3.13) est **en lecture seule** en V1.

- Navigation par année fiscale.
- Chaque règle est rendue via `describeForUI()`, qui produit une description structurée (nom, type, période, caractéristiques consommées/produites, paramètres, exemple chiffré, base légale).
- Rendu HTML généré côté serveur à partir de cette description, pas hardcodé.
- **Aucune mention** de confiance, d'incertitude, de « à valider ». L'application présente la règle comme elle s'applique.

---

### 7. Temporalité — trois temps distincts

Le moteur gère explicitement trois temporalités :

| Temps | Concerne | Géré par |
|---|---|---|
| **Période d'application des règles** | « Cette règle s'applique du 01/01/2024 au 31/12/2024 » | Champ `applicabilityPeriod` de la règle |
| **Caractéristiques véhicule historisées** | « Le 12/03/2024, ce véhicule est passé d'essence à E85 » | Table `vehicle_fiscal_characteristics` avec date d'effet |
| **Année fiscale du calcul** | « Calcul de la taxe due par ACME pour 2024 » | Paramètre du contexte d'invocation |

Le moteur croise ces trois temporalités à chaque calcul : il sélectionne les règles applicables à l'année fiscale, lit les caractéristiques véhicule effectives à la date de chaque attribution, et produit le résultat.

---

## Justification

### Pourquoi une interface de base + sous-types, et pas une interface unique ?

Les 24 règles concrètes du catalogue 2024 ont montré que les règles diffèrent sémantiquement (une `TarificationRule` produit un montant, une `ExonerationRule` produit une décision booléenne, une `ClassificationRule` produit une énumération). Forcer toutes ces différences dans une même signature créerait du bruit et rendrait le code de l'orchestrateur plein de `switch` sur le « vrai type » de la règle. Les sous-types permettent à l'orchestrateur d'invoquer le bon contrat au bon moment, avec un code lisible.

### Pourquoi un pipeline fixe ?

Avec 24 règles en V1 et probablement 30-50 règles en V3 (avec 2025 et 2026), un pipeline fixe reste maîtrisable. Un moteur de dépendances dynamique (où les règles se déclarent leurs dépendances et l'ordre est calculé automatiquement) serait plus flexible mais beaucoup plus complexe à développer, à tester, et à déboguer. Le coût n'est pas justifié.

Le pipeline fixe reste ouvert à évolution : si une situation future nécessite de changer l'ordre, on modifie le code du pipeline. C'est un changement ponctuel et contrôlé.

### Pourquoi code + base, et pas uniquement l'un ou l'autre ?

- **Code seul** : pas de consultation utilisateur possible (ADR-0001), pas de snapshot référable, pas d'analyse de dépendances par requête.
- **Base seule** (DSL) : complexité insurmontable pour exprimer des règles comme la LCD à cumul par couple, impossibilité de tests unitaires, fragilité (cf. ADR-0001 alternative 2).
- **Code + base** : la base sert d'index consultable et auditable, le code porte la logique testable et versionnée. Chaque responsabilité dans le bon endroit.

### Pourquoi un moteur unique pour trois modes ?

Cohérence et confiance. Si le compteur affiché à l'utilisateur au moment de l'attribution diverge, même légèrement, du calcul final produit dans le PDF, la crédibilité de l'application s'effondre. Un moteur unique, trois modes d'invocation : même code, mêmes règles, résultats strictement cohérents par construction.

### Pourquoi pas de champ confidence/associatedUncertainty dans l'interface ?

Cf. ADR-0001 et discussion finale ayant conduit à cette architecture : l'application doit présenter les règles comme la **vérité fiscale appliquée**, pas comme une hypothèse parmi d'autres. Les débats internes (niveaux de confiance, incertitudes en cours de validation par l'expert-comptable) se tiennent dans la documentation projet, qui fait le pont entre le développement interne et l'interprétation client. Mélanger les deux dans l'application créerait de la confusion et fragiliserait la confiance utilisateur.

## Alternatives écartées

### Alternative 1 — Interface unique (pas de sous-types)

Une seule `FiscalRule` avec une méthode générique `evaluate(context) → result`.

**Rejetée** : contrat générique obligeant chaque appelant à faire du typage dynamique ou du switch. Code plus obscur, tests plus complexes, couplage plus fort entre règles et orchestrateur.

### Alternative 2 — Moteur de dépendances dynamique

Chaque règle déclare ses dépendances (caractéristiques consommées / produites), le moteur calcule l'ordre d'exécution automatiquement.

**Repoussée** (pas « rejetée ») : trop lourd pour V1. Le pipeline fixe suffit pour les 24 règles actuelles et restera probablement suffisant pour 2025/2026 qui sont des variations sur le même schéma. Si la complexité croît significativement, le moteur de dépendances pourra être ajouté en V3+.

### Alternative 3 — Règles en base uniquement avec DSL d'expression

Toutes les règles exprimées comme des formules textuelles évaluées à la volée.

**Rejetée** : cf. ADR-0001 alternative 2. Impossibilité d'exprimer la LCD avec cumul par couple dans un DSL simple sans réinventer un langage de programmation.

### Alternative 4 — Trois moteurs distincts pour les trois modes

Un moteur de calcul annuel, un moteur de simulation, un moteur de génération PDF — chacun optimisé pour son usage.

**Rejetée** : divergences inévitables entre trois implémentations, coût de maintenance multiplié, atteinte à la confiance utilisateur.

### Alternative 5 — Ajout des champs confidence/associatedUncertainty dans l'interface

Inclure ces champs pour permettre l'affichage et la gestion dans l'application.

**Rejetée** (après discussion explicite) : confondre application et documentation. L'application doit présenter la vérité fiscale appliquée, les débats se font dans la documentation. Cf. ADR-0001 (rôle pont de la documentation).

## Conséquences

### Conséquences positives

- **Cohérence forte** entre les trois modes d'exécution (calcul, simulation, PDF).
- **Modularité** : ajouter une nouvelle règle consiste à créer une classe implémentant le sous-type approprié et à déclarer sa métadonnée en base par seeder.
- **Auditabilité** : chaque calcul est traçable via les snapshots, chaque règle est consultable.
- **Évolutivité** : ajouter une année fiscale (2025, 2026…) consiste à seeder les règles de l'année dans le catalogue. Le moteur ne bouge pas.
- **Testabilité** : chaque règle a une signature claire (selon son sous-type) et est testable unitairement.
- **Séparation claire application / documentation** : l'application est nette, la documentation porte les débats.

### Conséquences techniques

- Structure du repository : dossier `rules/{année}/{catégorie}/` avec une classe par règle.
- Table `fiscal_rules` en base, alimentée par seeders.
- Module `orchestrator` qui exécute le pipeline fixe.
- Module `snapshot` qui capture l'état à chaque génération PDF.
- Module `validator` qui vérifie au démarrage la cohérence du pipeline (caractéristiques consommées disponibles).
- Module `consultor` qui rend les règles pour la page de consultation.

### Conséquences produit

- La page « Fiscalité → Règles de calcul » est un écran de consultation navigable par année.
- Les vues planning (cahier des charges § 3.4) peuvent afficher en temps réel le compteur LCD par couple grâce au mode simulation.
- Le PDF récapitulatif contient la ventilation détaillée par couple, règle par règle, pour audit.

### Conséquences organisationnelles

- L'équipe technique (prestataire) a la responsabilité de produire des règles de qualité, conformément au processus documenté dans `project-management/recherches-fiscales/methodologie.md`.
- Les retours client se traduisent par des modifications de règles (code + seeder) déployées via le processus standard.

## Liens

- ADR-0001 — La fiscalité est une donnée, pas du code
- ADR-0002 — Règles non éditables depuis l'application en V1
- ADR-0003 — PDF et snapshots immuables des déclarations
- ADR-0004 — Invalidation de déclarations par marquage
- ADR-0005 — Calcul fiscal jour-par-jour
- `project-management/taxes-rules/2024.md` — catalogue de règles 2024 qui a nourri cette architecture
- `project-management/recherches-fiscales/methodologie.md` — cadre de production des règles
- `project-management/cahier_des_charges.md` § 3.13 — page de consultation des règles

---

## Historique

| Version | Date | Auteur | Modifications |
|---|---|---|---|
| 1.0 | 23/04/2026 | Micha MEGRET | Rédaction initiale — formalisation des 7 décisions d'architecture du moteur de règles, prises après la phase de recherche fiscale 2024 (avec les 24 règles concrètes du catalogue 2024 comme base de calibration). |
