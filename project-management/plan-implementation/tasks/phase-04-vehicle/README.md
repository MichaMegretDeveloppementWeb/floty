# Phase 04 — Domaine Vehicle (modèle pour les domaines suivants)

## Objectif de la phase

Implémenter le **premier domaine métier complet** de Floty : les véhicules. Cette phase est le **modèle structurel** qui sera réutilisé pour les domaines Company, Driver, Assignment, Unavailability.

Particularités Vehicle :

- **Historisation** des caractéristiques fiscales (`vehicle_fiscal_characteristics`) via périodes `effective_from`/`effective_to`.
- **Triggers MySQL** anti-chevauchement de périodes (cf. `01-schema-metier.md § 0.3`).
- **4 variantes Data DTO** : `VehicleData`, `VehicleListItemData`, `VehicleFormData`, `VehicleStoreData`.
- Nombreux Enums métier : `VehicleUserType`, `EnergySource`, `HomologationMethod`, `PollutantCategory`, `EuroStandard`, `BodyType`, `FiscalCharacteristicsChangeReason`.

## Dépendances

Phase 01 + 02 + 03 terminées.

## Tâches

### 04.A — Migrations & modèles

| N° | Tâche | Statut |
|---|---|---|
| 04.01 | [Migration `vehicles` (18 champs, UNIQUE `license_plate` via colonne générée)](01-migration-vehicles.md) | À faire |
| 04.02 | [Migration `vehicle_fiscal_characteristics` (historisation avec `effective_from`/`effective_to`)](02-migration-vehicle-fiscal-characteristics.md) | À faire |
| 04.03 | [Migration dédiée aux triggers MySQL anti-chevauchement (`BEFORE INSERT/UPDATE`)](03-migration-triggers-overlap.md) | À faire |
| 04.04 | [Model `Vehicle` (SoftDeletes, relation hasMany fiscalCharacteristics, accessor currentFiscalCharacteristics)](04-model-vehicle.md) | À faire |
| 04.05 | [Model `VehicleFiscalCharacteristics` (belongsTo vehicle)](05-model-vehicle-fiscal-characteristics.md) | À faire |

### 04.B — Enums

| N° | Tâche | Statut |
|---|---|---|
| 04.06 | [Enums Vehicle : `VehicleUserType`, `EnergySource`, `HomologationMethod`, `PollutantCategory`, `EuroStandard`, `BodyType`, `FiscalCharacteristicsChangeReason`](06-enums-vehicle.md) | À faire |

### 04.C — Data DTO (Spatie)

| N° | Tâche | Statut |
|---|---|---|
| 04.07 | [`VehicleData` (représentation complète + currentFiscalCharacteristics + fiscalCharacteristicsHistory)](07-data-vehicle.md) | À faire |
| 04.08 | [`VehicleListItemData` (liste allégée pour Index)](08-data-vehicle-list-item.md) | À faire |
| 04.09 | [`VehicleFormData` + `VehicleStoreData` (formulaire + DTO d'entrée)](09-data-vehicle-form.md) | À faire |
| 04.10 | [`VehicleFiscalCharacteristicsData` (représentation + form)](10-data-vehicle-fiscal-characteristics.md) | À faire |
| 04.11 | [Régénérer `resources/js/types/generated.d.ts` + ré-export dans `types/index.ts`](11-regenerate-ts-types.md) | À faire |

### 04.D — Repositories, Services, Actions

| N° | Tâche | Statut |
|---|---|---|
| 04.12 | [Interface + implémentation `VehicleListReadRepository`](12-repository-vehicle-list-read.md) | À faire |
| 04.13 | [Interface + implémentation `VehicleFiscalCharacteristicsReadRepository` + `WriteRepository`](13-repository-vehicle-fiscal-characteristics.md) | À faire |
| 04.14 | [Service `VehicleCreationService`](14-service-vehicle-creation.md) | À faire |
| 04.15 | [Service `VehicleFiscalCharacteristicsService` (avec validation invariants WLTP↔co2, hybride↔moteur thermique)](15-service-vehicle-fiscal-characteristics.md) | À faire |
| 04.16 | [Action `CreateVehicleAction` (transaction + creation initiale fiscal characteristics)](16-action-create-vehicle.md) | À faire |
| 04.17 | [Action `UpdateVehicleAction`](17-action-update-vehicle.md) | À faire |
| 04.18 | [Actions `SoftDeleteVehicleAction` + `HardDeleteVehicleAction`](18-action-delete-vehicle.md) | À faire |
| 04.19 | [Actions `CreateNewEffectiveVersionAction` + `CorrectExistingVersionAction` (historisation fiscal)](19-action-fiscal-characteristics.md) | À faire |

### 04.E — Exceptions

| N° | Tâche | Statut |
|---|---|---|
| 04.20 | [Exceptions `VehicleCreationException`, `VehicleUpdateException`, `VehicleNotFoundException`, `VehicleFiscalCharacteristicsValidationException`](20-exceptions-vehicle.md) | À faire |

### 04.F — Http (FormRequest, Controller, Policy, Routes)

| N° | Tâche | Statut |
|---|---|---|
| 04.21 | [FormRequest `StoreVehicleRequest` + `UpdateVehicleRequest`](21-form-requests-vehicle.md) | À faire |
| 04.22 | [Controller `User/Vehicle/VehicleController` (resource : index, show, create, store, edit, update, destroy)](22-controller-vehicle.md) | À faire |
| 04.23 | [Controller `User/VehicleFiscalCharacteristics/VehicleFiscalCharacteristicsController`](23-controller-vehicle-fiscal-characteristics.md) | À faire |
| 04.24 | [Policy `VehiclePolicy` (V1 tous authentifiés peuvent tout, V2 rôles)](24-policy-vehicle.md) | À faire |
| 04.25 | [Routes `routes/user.php` + resource controller](25-routes-vehicle.md) | À faire |

### 04.G — Pages Vue (Inertia)

| N° | Tâche | Statut |
|---|---|---|
| 04.26 | [`Pages/User/Vehicles/Index/Index.vue` + Partials (ListHeader, Filters, Table, EmptyState)](26-page-vehicles-index.md) | À faire |
| 04.27 | [`Pages/User/Vehicles/Show/Show.vue` + Partials (Summary, FiscalCharacteristicsTable, FiscalHistoryTimeline, AssignmentsTimeline, UnavailabilitiesList)](27-page-vehicles-show.md) | À faire (parts liées aux autres domaines arrivent plus tard) |
| 04.28 | [`Pages/User/Vehicles/Create/Create.vue` + Partials (VehicleForm)](28-page-vehicles-create.md) | À faire |
| 04.29 | [`Pages/User/Vehicles/Edit/Edit.vue` + Partials (VehicleForm partagé)](29-page-vehicles-edit.md) | À faire |
| 04.30 | [Composant `Components/Domain/Vehicle/VehicleCard.vue` + `VehicleStatusBadge.vue`](30-components-domain-vehicle.md) | À faire |

### 04.H — Tests

| N° | Tâche | Statut |
|---|---|---|
| 04.31 | [Tests Feature `VehicleControllerTest` (index, store, update, destroy)](31-tests-feature-vehicle-controller.md) | À faire |
| 04.32 | [Tests Unit `VehicleFiscalCharacteristicsServiceTest` (validation invariants)](32-tests-unit-vehicle-fiscal-characteristics-service.md) | À faire |
| 04.33 | [Tests Feature `CreateVehicleActionTest` (transaction, rollback, historisation initiale)](33-tests-feature-create-vehicle-action.md) | À faire |
| 04.34 | [Tests Vitest frontend (VehicleCard, VehicleForm, pages Index/Show smoke tests)](34-tests-frontend-vehicle.md) | À faire |

## Critère de complétion de la phase

- CRUD véhicule fonctionne bout en bout (création → liste → détail → édition → suppression soft + hard avec modal).
- Historisation des caractéristiques fiscales fonctionne : créer une nouvelle version ferme la précédente, les chevauchements sont rejetés par le trigger ET par le service.
- Tous les types TS sont générés et consommés sans `any`.
- Tests back + front passent.
- Revue Laravel Boost `database-schema` : tables cohérentes avec `01-schema-metier.md`.

## Documents liés

- [`docs/migration-vehicles.md`](../../docs/migration-vehicles.md) — schéma détaillé et contraintes MySQL.
- [`docs/migration-vehicle-fiscal-characteristics.md`](../../docs/migration-vehicle-fiscal-characteristics.md) — historisation + triggers.
- [`docs/vehicle-fiscal-characteristics-service.md`](../../docs/vehicle-fiscal-characteristics-service.md) — logique métier du service (invariants, transitions).
- [`docs/data-dto-variants-pattern.md`](../../docs/data-dto-variants-pattern.md) — pattern des 4 variantes (Data/ListItemData/FormData/StoreData) applicable à tous les domaines.
- [`docs/crud-pages-pattern.md`](../../docs/crud-pages-pattern.md) — pattern commun pour les pages Index/Show/Create/Edit (applicable aux phases 05-08).

## Références

- `modele-de-donnees/01-schema-metier.md` § 2 et § 3
- `modele-de-donnees/03-strategie-suppression.md`
- `implementation-rules/architecture-solid.md` § 4-7
- `implementation-rules/typescript-dto.md`
- `implementation-rules/vue-composants.md`
- `implementation-rules/inertia-navigation.md`
- `implementation-rules/tests-frontend.md`
- ADR-0005 (calcul jour par jour → pas de vehicle_fiscal_characteristics)
- ADR-0006 (moteur de règles → consomme ces données)
