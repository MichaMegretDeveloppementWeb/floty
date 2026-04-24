# Phase 10 — Moteur fiscal (cœur métier)

## Objectif de la phase

Implémenter le **moteur de règles fiscales** qui calcule les taxes CO₂ et polluants dues par chaque company pour chaque année. C'est le **cœur de Floty** — sans ce moteur, le produit n'a pas de valeur.

Référence architecture : **ADR-0006** (7 décisions du moteur de règles) + `taxes-rules/2024.md` (catalogue de 24 règles R-2024-001 à R-2024-024).

## Dépendances

Phase 04 (Vehicle + VehicleFiscalCharacteristics) + Phase 07 (Assignment) + Phase 08 (Unavailability) terminées.

## Tâches

### 10.A — Infrastructure du moteur

| N° | Tâche | Statut |
|---|---|---|
| 10.01 | [Migration `fiscal_rules` (cf. 02-schema-fiscal.md § 1)](01-migration-fiscal-rules.md) | À faire |
| 10.02 | [Model `FiscalRule`](02-model-fiscal-rule.md) | À faire |
| 10.03 | [Enums `RuleType` (Classification, Pricing, Exemption, Abatement, Transversal) + `TaxType` (Co2, Pollutants)](03-enums-fiscal.md) | À faire |
| 10.04 | [Interface PHP `FiscalRule` (abstract class avec les 8 propriétés ADR-0006 § 1)](04-interface-fiscal-rule.md) | À faire |
| 10.05 | [5 sous-classes abstraites : `ClassificationRule`, `TarificationRule`, `ExonerationRule`, `AbatementRule`, `TransversalRule`](05-rule-subtypes.md) | À faire |
| 10.06 | [Service `FiscalRuleRegistry` (charge les règles actives d'une année depuis `fiscal_rules` + code_reference)](06-service-fiscal-rule-registry.md) | À faire |
| 10.07 | [Service `FiscalRulePipeline` (exécute le pipeline 8 étapes — ADR-0006 § 2)](07-service-fiscal-rule-pipeline.md) | À faire |
| 10.08 | [Service `FiscalRuleEngine` (orchestration complète pour une déclaration, 3 modes : calcul/simulation/PDF)](08-service-fiscal-rule-engine.md) | À faire |
| 10.09 | [Service `FiscalRuleConsistencyValidator` (vérifie au boot que les caractéristiques consommées sont disponibles — ADR-0006 § 2 fin)](09-service-consistency-validator.md) | À faire |
| 10.10 | [Repository `FiscalRuleRegistryReadRepository`](10-repository-fiscal-rule-registry-read.md) | À faire |

### 10.B — Implémentation des 24 règles 2024

Chaque règle du catalogue `taxes-rules/2024.md` devient une classe PHP dans `rules/2024/{categorie}/{nom}.php`.

| N° | Tâche | Statut |
|---|---|---|
| 10.11 | [Règles transversales (R-2024-001 à R-2024-003 : année civile, années bissextiles, arrondi)](11-rules-transversal.md) | À faire |
| 10.12 | [Règles de classification (R-2024-004 à R-2024-006 : M1/N1, barème CO₂, bascule PA)](12-rules-classification.md) | À faire |
| 10.13 | [Règles transversales de contexte (R-2024-007 à R-2024-009 : prorata jour, catégories polluants)](13-rules-transversal-context.md) | À faire |
| 10.14 | [Règles de tarification (R-2024-010 à R-2024-012 : barèmes WLTP, NEDC, PA)](14-rules-pricing.md) | À faire |
| 10.15 | [Règles de tarification polluants (R-2024-013 à R-2024-014 : catégorisation + tarif forfaitaire)](15-rules-pricing-pollutants.md) | À faire |
| 10.16 | [Règles d'exonération (R-2024-015 à R-2024-022 : handicap, usages spéciaux, entreprises d'intérêt général, activités, LCD cumul par couple)](16-rules-exemption.md) | À faire (la plus dense, notamment R-2024-021 LCD) |
| 10.17 | [Règles complémentaires (R-2024-023 à R-2024-024 : calendrier déclaration, arrondi PDF)](17-rules-complementary.md) | À faire |

### 10.C — Seeder + tests

| N° | Tâche | Statut |
|---|---|---|
| 10.18 | [`Rules2024Seeder` (enregistre les 24 règles en BDD avec leurs métadonnées : code_reference, legal_basis, applicability_period, etc.)](18-seeder-rules-2024.md) | À faire |
| 10.19 | [Tests unitaires par règle (au moins 1 test par règle, cas nominaux + edge cases)](19-tests-rules.md) | À faire |
| 10.20 | [Test d'intégration du pipeline complet (cas synthétiques : véhicule VP essence WLTP 2024, hybride rechargeable, LCD cumul < 30 jours, LCD cumul > 30 jours, etc.)](20-tests-pipeline-integration.md) | À faire |
| 10.21 | [Test `FiscalRuleConsistencyValidator` : démarrage détecte bien les incohérences](21-test-consistency-validator.md) | À faire |

### 10.D — Page de consultation règles (lecture seule)

| N° | Tâche | Statut |
|---|---|---|
| 10.22 | [`FiscalRule::describeForUI()` (retour `RuleDescription` typée)](22-rule-describe-for-ui.md) | À faire |
| 10.23 | [Data `FiscalRuleDisplayData`](23-data-fiscal-rule-display.md) | À faire |
| 10.24 | [Controller `User/Declaration/FiscalRuleController` (index, show par année)](24-controller-fiscal-rule.md) | À faire |
| 10.25 | [Page `Pages/User/Declarations/Rules/Rules.vue` + Partials (RulesYearSelector, RuleCard)](25-page-fiscal-rules.md) | À faire |

## Critère de complétion

- Les 24 règles 2024 sont implémentées en PHP.
- Le seeder enregistre les 24 règles avec toutes leurs métadonnées.
- Chaque règle est testée unitairement (tests passent).
- Un test d'intégration de pipeline produit le bon résultat sur au moins 5 scénarios métier (véhicule essence WLTP, diesel, hybride rechargeable, LCD < 30j, LCD > 30j).
- La page de consultation `/app/declarations/rules` affiche proprement les 24 règles avec leur description, base légale, caractéristiques consommées/produites.

## Documents liés

- [`docs/fiscal-rule-architecture.md`](../../docs/fiscal-rule-architecture.md) — anatomie d'une classe Rule Floty.
- [`docs/fiscal-rule-pipeline-stages.md`](../../docs/fiscal-rule-pipeline-stages.md) — détails des 8 étapes du pipeline.
- [`docs/rule-R-2024-021-lcd-cumul.md`](../../docs/rule-R-2024-021-lcd-cumul.md) — la règle la plus complexe (exonération LCD avec cumul par couple).
- [`docs/fiscal-rule-consistency-check.md`](../../docs/fiscal-rule-consistency-check.md) — vérification cohérence caractéristiques consommées/produites au boot.

## Références

- ADR-0001 (fiscalité comme donnée)
- ADR-0002 (règles non éditables via UI)
- ADR-0005 (calcul jour par jour)
- **ADR-0006** (architecture moteur de règles — document principal)
- `taxes-rules/2024.md` — catalogue des 24 règles (source de vérité)
- `recherches-fiscales/2024/` — contexte et décisions derrière les règles
- `modele-de-donnees/02-schema-fiscal.md`
