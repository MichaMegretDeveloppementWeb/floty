# Phase 05 — Domaine Company

## Objectif de la phase

Implémenter le domaine **Company** (entreprises utilisatrices — cf. `02-schema-fiscal.md`). Phase plus simple que Vehicle : pas d'historisation, pas de triggers complexes. Reprend intégralement les patterns de la phase 04.

## Dépendances

Phase 04 terminée (pour hériter du pattern).

## Tâches

| N° | Tâche | Statut |
|---|---|---|
| 05.01 | [Migration `companies` (legal_name, SIREN, SIRET, adresse, contact, short_code, color, is_active, deactivated_at)](01-migration-companies.md) | À faire |
| 05.02 | [Model `Company` (SoftDeletes, relation hasMany drivers, hasMany assignments)](02-model-company.md) | À faire |
| 05.03 | [Data DTO : `CompanyData`, `CompanyListItemData`, `CompanyFormData`, `CompanyStoreData`](03-data-company.md) | À faire |
| 05.04 | [FormRequest `StoreCompanyRequest` + `UpdateCompanyRequest` (validation SIREN via `isValidSiren` util)](04-form-requests-company.md) | À faire |
| 05.05 | [Service `CompanyCreationService` + `CompanyDeactivationService`](05-services-company.md) | À faire |
| 05.06 | [Actions `CreateCompanyAction`, `UpdateCompanyAction`, `DeactivateCompanyAction`](06-actions-company.md) | À faire |
| 05.07 | [Exceptions `CompanyCreationException`, `CompanyNotFoundException`](07-exceptions-company.md) | À faire |
| 05.08 | [Controller `User/Company/CompanyController` (resource CRUD)](08-controller-company.md) | À faire |
| 05.09 | [Policy `CompanyPolicy`](09-policy-company.md) | À faire |
| 05.10 | [Pages Vue `Pages/User/Companies/Index/` + `Show/` + `Create/` + `Edit/` (avec partials)](10-pages-companies.md) | À faire |
| 05.11 | [Composants `Components/Domain/Company/CompanyBadge.vue` (code court coloré), `CompanySelector.vue`](11-components-domain-company.md) | À faire |
| 05.12 | [Util `isValidSiren(siren: string): boolean` (algorithme Luhn) dans `app/Support/Validation/FrenchIdentifiers.php` + double TS dans `resources/js/Utils/validation/frenchSiren.ts`](12-util-is-valid-siren.md) | À faire |
| 05.13 | [`CompanyFactory` + `DemoCompaniesSeeder` (~8 entreprises réalistes — couleurs variées, SIREN valides)](13-factory-seeder-company.md) | À faire |
| 05.14 | [Tests Feature CompanyController + Unit CompanyCreationService + `isValidSiren`](14-tests-company.md) | À faire |

## Critère de complétion

- CRUD Company bout en bout.
- Validation SIREN (algorithme Luhn) côté FormRequest ET côté Vue (via `isValidSiren` util).
- `CompanyBadge` affiche le code court coloré utilisé dans la vue véhicule (phase 04.27 timeline).

## Documents liés

- [`docs/migration-companies.md`](../../docs/migration-companies.md)
- [`docs/company-badge-component.md`](../../docs/company-badge-component.md) — composant Domain transverse (utilisé dans heatmap, timeline véhicule, etc.)

## Références

- `modele-de-donnees/01-schema-metier.md` § 4
- Pattern identique à phase 04
