# Phase 07 — Domaine Assignment (entité pivot centrale)

## Objectif de la phase

Implémenter le **domaine pivot** de Floty : les attributions (cf. ADR-0005). Une ligne par `(vehicle_id, date)` — granularité jour. C'est sur ces données que s'appuient :

- Le compteur LCD par couple (phase 09)
- Le moteur fiscal (phase 10)
- La génération de déclarations (phase 11)

**Critique** : la contrainte `UNIQUE(vehicle_id, date)` filtrée par soft delete est assurée via **colonne générée** en MySQL (cf. `01-schema-metier.md § 0.2`).

## Dépendances

Phase 04 + 05 + 06 terminées.

## Tâches

| N° | Tâche | Statut |
|---|---|---|
| 07.01 | [Migration `assignments` (vehicle_id, company_id, driver_id nullable, date, timestamps, soft delete) + colonne générée `vehicle_id_date_active` pour UNIQUE](01-migration-assignments.md) | À faire |
| 07.02 | [Model `Assignment` (SoftDeletes, belongsTo vehicle/company/driver)](02-model-assignment.md) | À faire |
| 07.03 | [Data DTO : `AssignmentData`, `WeeklyAssignmentData` (pour bulk weekly entry)](03-data-assignment.md) | À faire |
| 07.04 | [FormRequests `StoreAssignmentRequest`, `BulkSaveWeeklyAssignmentsRequest`](04-form-requests-assignment.md) | À faire |
| 07.05 | [Service `LcdCumulCalculationService` (calcul du cumul jours par couple + détection impact fiscal fourrière)](05-service-lcd-cumul.md) | À faire |
| 07.06 | [Service `AssignmentConflictResolver` (validation `UNIQUE(vehicle_id, date)` + préconditions date vs vehicle.date_acquisition/exit)](06-service-assignment-conflict-resolver.md) | À faire |
| 07.07 | [Service `BulkAssignmentService` (batch avec transaction + cache invalidation tags)](07-service-bulk-assignment.md) | À faire |
| 07.08 | [Interface + impl `LcdCumulReadRepository` (requête optimisée `SELECT COUNT(*)` groupée, index sur (vehicle_id, company_id, year))](08-repository-lcd-cumul.md) | À faire |
| 07.09 | [Interface + impl `WeeklyAssignmentWriteRepository` (bulk insert/update)](09-repository-weekly-assignment-write.md) | À faire |
| 07.10 | [Actions `CreateAssignmentAction`, `UpdateAssignmentAction`, `DeleteAssignmentAction`, `BulkSaveWeeklyAssignmentsAction`](10-actions-assignment.md) | À faire |
| 07.11 | [Exceptions `AssignmentConflictException`, `AssignmentListException`, `AssignmentBatchException`](11-exceptions-assignment.md) | À faire |
| 07.12 | [Observer `AssignmentObserver` (invalide cache tags sur create/update/delete)](12-observer-assignment.md) | À faire |
| 07.13 | [Event `AssignmentChanged` (émission uniquement — les listeners qui déclenchent l'invalidation des déclarations sont branchés en phase 11 après la création du service `DeclarationInvalidationDetector`)](13-event-assignment-changed.md) | À faire |
| 07.14 | [Controller `User/Assignment/AssignmentController`](14-controller-assignment.md) | À faire |
| 07.15 | [`AssignmentFactory` + `DemoAssignmentsSeeder` (~6 mois d'assignments répartis sur la flotte demo, mix LCD/LLD)](15-factory-seeder-assignment.md) | À faire |
| 07.16 | [Tests Feature + Unit (notamment conflits, bulk, cumul LCD)](16-tests-assignment.md) | À faire |

## Critère de complétion

- Création / modification / suppression d'assignment avec gestion du conflit `UNIQUE(vehicle_id, date)`.
- `LcdCumulCalculationService` retourne le bon cumul pour un couple (véhicule, company) sur une année donnée, en prenant en compte les indispos `pound` (fourrière) qui ne comptent pas.
- Bulk save de 50+ assignments en une transaction atomique.
- Cache tags invalidés correctement après chaque mutation.
- Event `AssignmentChanged` bien émis (le listener d'invalidation des déclarations est branché en phase 11 pour éviter une dépendance circulaire avec `DeclarationInvalidationDetector`).

## Documents liés

- [`docs/assignment-unique-constraint-mysql.md`](../../docs/assignment-unique-constraint-mysql.md) — technique de colonne générée pour l'index partiel sur MySQL.
- [`docs/lcd-cumul-calculation.md`](../../docs/lcd-cumul-calculation.md) — logique précise du calcul LCD (règle R-2024-021 du catalogue fiscal).
- [`docs/cache-tags-invalidation.md`](../../docs/cache-tags-invalidation.md) — stratégie complète de cache tags + observer.

## Références

- `modele-de-donnees/01-schema-metier.md` § 6
- `modele-de-donnees/04-strategie-cache.md`
- `taxes-rules/2024.md` — R-2024-021 (exonération LCD cumul par couple)
- ADR-0005 (calcul jour par jour)
- ADR-0004 (invalidation par marquage)
