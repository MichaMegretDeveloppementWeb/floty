# Phase 08 — Domaine Unavailability

## Objectif de la phase

Implémenter le domaine **Unavailability** (indisponibilités véhicule : maintenance, contrôle technique, accident, fourrière, autre). Particularité : seul le type `pound` (fourrière) a un **impact fiscal** (`has_fiscal_impact = true`) — les autres ne réduisent pas le prorata.

## Dépendances

Phase 04 (Vehicle) + 07 (Assignment pour le listener d'invalidation) terminées.

## Tâches

| N° | Tâche | Statut |
|---|---|---|
| 08.01 | [Migration `unavailabilities` (vehicle_id FK, type, has_fiscal_impact, start_date, end_date nullable, description) + CHECK `has_fiscal_impact = (type = 'pound')`](01-migration-unavailabilities.md) | À faire |
| 08.02 | [Model `Unavailability` (SoftDeletes, belongsTo vehicle)](02-model-unavailability.md) | À faire |
| 08.03 | [Enum `UnavailabilityType` (Maintenance, TechnicalInspection, Accident, Pound, Other)](03-enum-unavailability-type.md) | À faire |
| 08.04 | [Data DTO `UnavailabilityData`, `UnavailabilityFormData`](04-data-unavailability.md) | À faire |
| 08.05 | [FormRequest avec validation (start_date ≤ end_date, cohérence has_fiscal_impact)](05-form-requests-unavailability.md) | À faire |
| 08.06 | [Service `UnavailabilityService` (gestion has_fiscal_impact dénormalisé, validation plages, détection overlap existant)](06-service-unavailability.md) | À faire |
| 08.07 | [Actions `CreateUnavailabilityAction`, `UpdateUnavailabilityAction`, `CloseUnavailabilityAction` (ajoute end_date)](07-actions-unavailability.md) | À faire |
| 08.08 | [Exceptions `UnavailabilityOverlapException`](08-exceptions-unavailability.md) | À faire |
| 08.09 | [Observer + event `UnavailabilityChanged` (invalide cache ; le listener qui déclenche la détection d'invalidation pour les indispos `pound` est branché en phase 11 après la création de `DeclarationInvalidationDetector`)](09-observer-unavailability.md) | À faire |
| 08.10 | [Controller `User/Unavailability/UnavailabilityController`](10-controller-unavailability.md) | À faire |
| 08.11 | [Pages Vue (formulaire de saisie indispo depuis la fiche véhicule)](11-pages-unavailability.md) | À faire |
| 08.12 | [`UnavailabilityFactory` + `DemoUnavailabilitiesSeeder` (~1-2 indispos par véhicule demo, dont au moins une fourrière pour tester l'impact fiscal)](12-factory-seeder-unavailability.md) | À faire |
| 08.13 | [Tests Feature + Unit (cohérence has_fiscal_impact, validation overlap)](13-tests-unavailability.md) | À faire |

## Critère de complétion

- CRUD Unavailability fonctionnel.
- Le CHECK constraint MySQL rejette une insertion où `has_fiscal_impact` ne correspond pas à `type = 'pound'`.
- Visuellement dans le formulaire : le type sélectionné affiche en temps réel si l'indisponibilité aura un impact fiscal (CDC § 2.5).
- Création d'une indispo `pound` émet `UnavailabilityChanged` correctement (le branchement du listener qui invalide les déclarations se fait en phase 11).

## Documents liés

- [`docs/unavailability-fiscal-impact.md`](../../docs/unavailability-fiscal-impact.md) — logique précise : quelle indispo compte, pourquoi seul `pound` (BOFiP § 190).

## Références

- `modele-de-donnees/01-schema-metier.md` § 7
- CDC § 2.5 (impact fiscal des indispos)
- `taxes-rules/2024.md` — R-2024-008 (fourrière → réduit numérateur prorata)
