# Phase 11 — Déclarations fiscales

## Objectif de la phase

Implémenter le module **Déclarations** : gestion des déclarations fiscales annuelles par company (statuts brouillon/vérifiée/générée/envoyée), détection d'invalidation automatique (ADR-0004), historique des PDF générés, consultation des règles appliquées.

**La génération PDF elle-même est en phase 12.**

## Dépendances

Phase 10 (moteur fiscal) terminée.

## Tâches

### 11.A — Infrastructure déclarations

| N° | Tâche | Statut |
|---|---|---|
| 11.01 | [Migration `declarations` (cf. 02-schema-fiscal.md § 2)](01-migration-declarations.md) | À faire |
| 11.02 | [Migration `declaration_pdfs` (cf. 02-schema-fiscal.md § 3) — immuables](02-migration-declaration-pdfs.md) | À faire |
| 11.03 | [Enums `DeclarationStatus` (Draft/Verified/Generated/Sent), `InvalidationReason`](03-enums-declaration.md) | À faire |
| 11.04 | [Models `Declaration`, `DeclarationPdf`](04-models-declaration.md) | À faire |
| 11.05 | [Data `DeclarationData`, `DeclarationListItemData`, `DeclarationCalculationResultData`, `DeclarationPdfData`](05-data-declaration.md) | À faire |

### 11.B — Actions et services

| N° | Tâche | Statut |
|---|---|---|
| 11.06 | [Service `DeclarationCalculationService` (invoque le moteur fiscal en mode calcul)](06-service-declaration-calculation.md) | À faire |
| 11.07 | [Service `DeclarationSnapshotService` (construit le snapshot JSON final — cf. ADR-0003 + 02-schema-fiscal.md § 3.5)](07-service-declaration-snapshot.md) | À faire |
| 11.08 | [Service `DeclarationStatusService` (validation transitions : draft→verified→generated→sent + retour vers draft possible)](08-service-declaration-status.md) | À faire |
| 11.09 | [Service `DeclarationInvalidationDetector` (calcule hash snapshot vs dernier PDF + applique invalidation — ADR-0004)](09-service-declaration-invalidation-detector.md) | À faire |
| 11.10 | [Action `CalculateDeclarationAction`](10-action-calculate-declaration.md) | À faire |
| 11.11 | [Action `ChangeDeclarationStatusAction`](11-action-change-declaration-status.md) | À faire |
| 11.12 | [Action `DetectDeclarationInvalidationAction` (lance pour une déclaration ou un batch)](12-action-detect-declaration-invalidation.md) | À faire |
| 11.12a | [Listeners `InvalidateDeclarationsOnAssignmentChanged` + `InvalidateDeclarationsOnPoundUnavailabilityChanged` (branchés sur les events émis en phase 07.13 et 08.09 — appelle `DetectDeclarationInvalidationAction`)](12a-listeners-invalidation-events.md) | À faire |

### 11.C — Repositories

| N° | Tâche | Statut |
|---|---|---|
| 11.13 | [Repository `DeclarationListReadRepository` (pagination + filtres par année/statut/invalidation)](13-repository-declaration-list.md) | À faire |
| 11.14 | [Repository `DeclarationWriteRepository` (persist + markAsInvalidated)](14-repository-declaration-write.md) | À faire |

### 11.D — Controllers + Pages

| N° | Tâche | Statut |
|---|---|---|
| 11.15 | [Controller `User/Declaration/DeclarationController` (index, show, update status)](15-controller-declaration.md) | À faire |
| 11.16 | [Policy `DeclarationPolicy`](16-policy-declaration.md) | À faire |
| 11.17 | [Page `Pages/User/Declarations/Index/Index.vue` + Partials (Filters, DeclarationsTable avec badge invalidation)](17-page-declarations-index.md) | À faire |
| 11.18 | [Page `Pages/User/Declarations/Show/Show.vue` + Partials (Summary, CalculationDetails, PdfHistory, InvalidationBadge)](18-page-declarations-show.md) | À faire |
| 11.19 | [Composant `Components/Domain/Declaration/DeclarationStatusBadge.vue` + `InvalidationBadge.vue`](19-components-declaration-badges.md) | À faire |

### 11.E — Tests

| N° | Tâche | Statut |
|---|---|---|
| 11.20 | [Tests Feature DeclarationController](20-tests-controller.md) | À faire |
| 11.21 | [Tests Unit DeclarationCalculationService + DeclarationSnapshotService + DeclarationInvalidationDetector](21-tests-services.md) | À faire |
| 11.22 | [Tests d'intégration : modifier une assignment → invalidation détectée automatiquement sur la déclaration concernée](22-tests-invalidation-cascade.md) | À faire |

## Critère de complétion

- Une déclaration peut être calculée depuis la page `Show`.
- Les statuts transitent correctement avec logs d'audit.
- L'invalidation est détectée quand on modifie une assignment, caractéristique véhicule, ou indispo — badge visible dans l'UI.
- L'historique des PDF générés est consultable (phase 12 alimentera les PDF réels).

## Documents liés

- [`docs/declaration-lifecycle.md`](../../docs/declaration-lifecycle.md) — cycle de vie d'une déclaration.
- [`docs/declaration-invalidation-flow.md`](../../docs/declaration-invalidation-flow.md) — détection d'invalidation via hash SHA-256.
- [`docs/declaration-snapshot-format.md`](../../docs/declaration-snapshot-format.md) — structure exacte du `snapshot_json`.

## Références

- ADR-0003 (PDF et snapshots immuables)
- ADR-0004 (invalidation par marquage)
- `modele-de-donnees/02-schema-fiscal.md`
