# Phase 09 — Planning views (cœur UX du produit)

## Objectif de la phase

Implémenter les **4 vues planning** qui constituent le cœur UX de Floty (cf. CDC § 3.3 à § 3.8) :

1. **Heatmap globale annuelle** (100 véhicules × 52 semaines, < 200 ms)
2. **Saisie hebdomadaire tableur** (grille multi-cellules avec raccourcis clavier)
3. **Vue par company** avec compteur LCD temps réel
4. **Vue par véhicule** (timeline 52 semaines colorées par company)
5. **Wizard d'attribution rapide** (modal 3 étapes)

Cette phase est la plus **dense en UX et performance** du projet. Cf. `performance-ui.md` pour les règles critiques (`shallowRef`, `v-memo`, anti-pattern skeleton/lazy).

## Dépendances

Phase 04 + 05 + 06 + 07 + 08 terminées.

## Tâches

### 09.A — Heatmap globale annuelle

| N° | Tâche | Statut |
|---|---|---|
| 09.01 | [Repository `HeatmapReadRepository` (requête agrégée véhicules × semaines)](01-repository-heatmap-read.md) | À faire |
| 09.02 | [Service `HeatmapAggregationService` (calcul densités)](02-service-heatmap-aggregation.md) | À faire |
| 09.03 | [Data `HeatmapCellData` + `HeatmapGridData`](03-data-heatmap.md) | À faire |
| 09.04 | [Controller `User/Planning/HeatmapController`](04-controller-heatmap.md) | À faire |
| 09.05 | [Page `Pages/User/Planning/Heatmap/Heatmap.vue` + Partials (Header, Grid, Legend, Filters)](05-page-heatmap.md) | À faire |
| 09.06 | [Composant `Components/Domain/Planning/HeatmapCell.vue` (passif, avec `v-memo`)](06-component-heatmap-cell.md) | À faire |
| 09.07 | [Composable `useHeatmapFilters` (typeVehicle, energySource, status) — state via query params](07-composable-heatmap-filters.md) | À faire |
| 09.08 | [Tests perf : < 200 ms render initial + filtre instantané (Vue DevTools)](08-perf-heatmap.md) | À faire |

### 09.B — Saisie hebdomadaire (tableur)

| N° | Tâche | Statut |
|---|---|---|
| 09.09 | [Controller `User/Planning/WeeklyEntryController` (GET + POST bulk)](09-controller-weekly-entry.md) | À faire |
| 09.10 | [Page `Pages/User/Planning/WeeklyEntry/WeeklyEntry.vue` + Partials (Header, Table, Row, Toolbar)](10-page-weekly-entry.md) | À faire |
| 09.11 | [Composable `useWeeklyEntrySelection` (sélection multi-cellules, ctrl+clic, shift+clic, raccourcis clavier)](11-composable-weekly-entry-selection.md) | À faire |
| 09.12 | [Composable `useKeyboardShortcuts` (enregistrement + teardown)](12-composable-keyboard-shortcuts.md) | À faire |
| 09.13 | [UX bulk submit (bouton Enregistrer → `BulkSaveWeeklyAssignmentsAction`)](13-bulk-submit.md) | À faire |
| 09.14 | [Tests Vitest pour composable sélection + clavier](14-tests-weekly-entry.md) | À faire |

### 09.C — Vue par company + compteur LCD temps réel

| N° | Tâche | Statut |
|---|---|---|
| 09.15 | [Controller `User/Planning/ByCompanyController`](15-controller-by-company.md) | À faire |
| 09.16 | [Page `Pages/User/Planning/ByCompany/ByCompany.vue` + Partials (CompanySelector, Grid, LcdCumulCounter)](16-page-by-company.md) | À faire |
| 09.17 | [Composant `Components/Domain/Planning/LcdCumulCounter.vue` (LCD affichant jours + seuil + impact fiscal)](17-component-lcd-cumul-counter.md) | À faire |
| 09.18 | [Composable `useLcdCumul` (lecture du Pinia store ou props Inertia selon archi)](18-composable-lcd-cumul.md) | À faire |
| 09.19 | [Mutation temps réel : clic cellule → `router.visit` avec `only: ['lcdCumuls']` + `preserveState`](19-lcd-real-time-mutation.md) | À faire |

### 09.D — Vue par véhicule

| N° | Tâche | Statut |
|---|---|---|
| 09.20 | [Controller `User/Planning/ByVehicleController`](20-controller-by-vehicle.md) | À faire |
| 09.21 | [Page `Pages/User/Planning/ByVehicle/ByVehicle.vue` + Partials (VehicleSelector, Timeline)](21-page-by-vehicle.md) | À faire |
| 09.22 | [Composant `Components/Domain/Planning/VehicleTimeline.vue` (SVG 52 segments colorés par company)](22-component-vehicle-timeline.md) | À faire |

### 09.E — Wizard d'attribution rapide

| N° | Tâche | Statut |
|---|---|---|
| 09.23 | [Controller `User/Planning/WizardAssignmentController` (POST bulk)](23-controller-wizard-assignment.md) | À faire |
| 09.24 | [Composant `Components/Domain/Planning/WizardAssignment.vue` (modal 3 étapes : qui/quand/quel véhicule)](24-component-wizard-assignment.md) | À faire |
| 09.25 | [État local du wizard via `ref` / `reactive` (stratégie A cf. inertia-navigation.md § wizard multi-étapes)](25-wizard-local-state.md) | À faire |

### 09.F — Panneau latéral drawer

| N° | Tâche | Statut |
|---|---|---|
| 09.26 | [Composant `Components/Domain/Planning/WeekDetailDrawer.vue` (ouverture au clic sur une cellule)](26-component-week-detail-drawer.md) | À faire |

## Critère de complétion

- Heatmap annuelle rend en < 200 ms (Vue DevTools profiler).
- Saisie hebdomadaire : sélection multi (ctrl/shift), raccourcis clavier (Enter pour attribuer, Delete pour effacer), bulk submit fonctionne.
- Compteur LCD s'incrémente/décrémente en temps réel au clic de cellule (partial reload Inertia, pas de full page reload).
- Wizard 3 étapes crée une attribution correctement.
- Drawer latéral ouvre au clic sur cellule avec détails semaine.
- Aucun lazy-loading systématique, aucun skeleton injustifié (cf. `performance-ui.md`).

## Documents liés

- [`docs/heatmap-architecture.md`](../../docs/heatmap-architecture.md) — rendu performant 5200 cellules (shallowRef, v-memo, CSS Grid).
- [`docs/weekly-entry-selection.md`](../../docs/weekly-entry-selection.md) — logique de sélection multi-cellules + clavier.
- [`docs/lcd-cumul-ui.md`](../../docs/lcd-cumul-ui.md) — pattern compteur LCD temps réel (backend recalcule, Inertia partial reload).
- [`docs/wizard-assignment-ux.md`](../../docs/wizard-assignment-ux.md) — UX des 3 étapes + validation.

## Références

- `implementation-rules/performance-ui.md`
- `implementation-rules/inertia-navigation.md` (partial reloads, deferred props)
- `implementation-rules/vue-composants.md`
- CDC § 3.3 à § 3.8
- ADR-0005, ADR-0006
