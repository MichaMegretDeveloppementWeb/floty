# Phase 06 — Domaine Driver

## Objectif de la phase

Implémenter le domaine **Driver** (conducteurs, rattachés à une company unique). Particularité : action `ReplaceDriverAction` qui transfère les assignments futurs d'un conducteur vers un autre.

## Dépendances

Phase 05 terminée.

## Tâches

| N° | Tâche | Statut |
|---|---|---|
| 06.01 | [Migration `drivers` (first_name, last_name, company_id FK RESTRICT, is_active, deactivated_at)](01-migration-drivers.md) | À faire |
| 06.02 | [Model `Driver` (SoftDeletes, belongsTo company, hasMany assignments)](02-model-driver.md) | À faire |
| 06.03 | [Data DTO : `DriverData`, `DriverFormData`](03-data-driver.md) | À faire |
| 06.04 | [FormRequests](04-form-requests-driver.md) | À faire |
| 06.05 | [Services `DriverCreationService`, `DriverReplacementService`](05-services-driver.md) | À faire |
| 06.06 | [Actions `CreateDriverAction`, `UpdateDriverAction`, `DeactivateDriverAction`, `ReplaceDriverAction` (transfert bulk des assignments futurs)](06-actions-driver.md) | À faire |
| 06.07 | [Exceptions `DriverNotFoundException`, `DriverReplacementException`](07-exceptions-driver.md) | À faire |
| 06.08 | [Controller `User/Driver/DriverController`](08-controller-driver.md) | À faire |
| 06.09 | [Policy `DriverPolicy`](09-policy-driver.md) | À faire |
| 06.10 | [Pages Vue `Pages/User/Drivers/Create/`, `Show/`, `Edit/` + composant `Components/Domain/Driver/DriverSelector.vue` + partial `DriverReplacementWizard.vue`](10-pages-drivers.md) | À faire |
| 06.11 | [`DriverFactory` + `DemoDriversSeeder` (~3 conducteurs par company de la seed demo)](11-factory-seeder-driver.md) | À faire |
| 06.12 | [Tests Feature + Unit (notamment ReplaceDriverAction)](12-tests-driver.md) | À faire |

## Critère de complétion

- CRUD Driver fonctionnel.
- Pattern `ReplaceDriverAction` testé : les assignments futurs (à partir d'une date pivot) passent à un nouveau conducteur de la **même company**, les assignments passées restent intactes.

## Documents liés

- [`docs/driver-replacement-action.md`](../../docs/driver-replacement-action.md) — logique précise du remplacement (contraintes, erreurs).

## Références

- `modele-de-donnees/01-schema-metier.md` § 5
- CDC § 2.3 (fonctionnalité « Remplacer par… »)
