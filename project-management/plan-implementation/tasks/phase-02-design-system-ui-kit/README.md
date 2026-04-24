# Phase 02 — Design system + UI Kit custom

> **Note d'avancement (2026-04-24)** : l'essentiel de cette phase a été réalisé en session avec validation visuelle au fil de l'eau sur `/dev/ui-kit`. Les tâches 02.01-02.14 sont **terminées**. Le client a décidé que les pages publiques seront gérées en custom hors UI Kit → la tâche 02.04 (`WebLayout`) est **annulée**. Restent les compléments listés en bas (DropdownMenu, Tooltip, gouvernance YearSelector) à traiter avant les phases qui en dépendent.

## Objectif de la phase

Traduire le design system Floty (`project-management/design-system/`, basé sur DM Sans + DM Mono + palette slate/blue + icônes lucide) en :

1. **Tokens Tailwind 4** (CSS `@theme` dans `resources/css/app.css`).
2. **Layout Inertia** : `UserLayout` (zone connectée) avec sidebar + top bar + zone toasts. **WebLayout retiré** — pages publiques traitées en custom hors UI Kit.
3. **UI Kit custom** dans `resources/js/Components/Ui/` : composants atomiques cohérents avec le design system.

**Pas de shadcn-vue** ni lib tierce — UI Kit 100 % maison (cf. ADR-0008).

## Dépendances

Phase 01 terminée.

## Tâches

| N° | Tâche | Statut |
|---|---|---|
| 02.01 | [Analyser le design system Floty (design-system/)](01-analyze-design-system.md) | À faire |
| 02.02 | [Traduire les tokens design en `@theme` Tailwind 4 dans `resources/css/app.css`](02-tailwind-theme-tokens.md) | À faire |
| 02.03 | [Installer DM Sans + DM Mono (via @fontsource ou lien Google Fonts)](03-install-fonts.md) | À faire |
| 02.04 | ~~Créer `Components/Layouts/WebLayout.vue`~~ | Annulée (décision client 2026-04-24 : pages publiques custom hors UI Kit) |
| 02.05 | [Créer `Components/Layouts/UserLayout.vue` + partials (Sidebar, TopBar, YearSelector, UserMenu, ToastContainer)](05-user-layout.md) | À faire |
| 02.06 | [UI Kit — `Button.vue` (variantes primary/secondary/ghost/danger + sizes + loading/disabled)](06-ui-button.md) | À faire |
| 02.07 | [UI Kit — `Input/TextInput.vue`, `Input/NumberInput.vue`, `Input/SelectInput.vue`, `Input/CheckboxInput.vue`, `Input/DateInput.vue`, `Input/InputError.vue`](07-ui-inputs.md) | À faire |
| 02.08 | [UI Kit — `Modal/Modal.vue` + `Modal/ConfirmModal.vue` (focus trap, escape, a11y)](08-ui-modal.md) | À faire |
| 02.09 | [UI Kit — `Drawer/Drawer.vue`](09-ui-drawer.md) | À faire |
| 02.10 | [UI Kit — `Toast/Toast.vue` + `Toast/ToastContainer.vue` + composable `useToast`](10-ui-toast.md) | À faire |
| 02.11 | [UI Kit — `Badge/Badge.vue` + `Card/Card.vue`](11-ui-badge-card.md) | À faire |
| 02.12 | [UI Kit — `Table/DataTable.vue` + `Table/DataTableColumn.vue`](12-ui-datatable.md) | À faire |
| 02.13 | [Utils format — `formatEuro.ts`, `formatDate.ts`, `formatLicensePlate.ts`, `formatSiren.ts`](13-utils-format.md) | À faire |
| 02.14 | [Tests Vitest pour chaque composant UI Kit (variantes, emits, a11y)](14-ui-kit-tests.md) | À faire |
| 02.15 | [Storybook des composants (page interne de démo)](15-ui-showcase-page.md) | À faire (optionnel mais utile pour validation visuelle) |
| 02.16 | [UI Kit — `DropdownMenu.vue` (menu contextuel clavier-a11y, utilisé par UserMenu + tables d'action ligne)](16-ui-dropdown-menu.md) | À faire |
| 02.17 | [UI Kit — `Tooltip.vue` (popover léger au hover, basé sur `@floating-ui/vue` si besoin de positionnement smart)](17-ui-tooltip.md) | À faire |
| 02.18 | [Gouvernance `YearSelector` : définir où vit l'année fiscale active (shared prop Inertia `fiscalYear` + persistance via session Laravel). Rédiger `docs/year-selector-governance.md` — composable `useFiscalYear`, navigation préservée à travers les changements d'année, invariants de l'URL.](18-year-selector-governance.md) | À faire |

## Critère de complétion de la phase

- `resources/css/app.css` contient les tokens complets du design system (couleurs, typo, rayons, espacements, ombres).
- `WebLayout` et `UserLayout` fonctionnent avec une page vide de test.
- L'ensemble UI Kit est utilisable, typé TS, testé Vitest.
- Une page démo (accessible en dev seulement) présente tous les composants UI Kit pour validation visuelle.

## Documents liés

- [`docs/design-system-translation.md`](../../docs/design-system-translation.md) — méthode de traduction des tokens design → Tailwind 4.
- [`docs/ui-button.md`](../../docs/ui-button.md) — spec du composant Button Floty.
- [`docs/ui-input.md`](../../docs/ui-input.md) — spec des inputs Floty.
- [`docs/ui-modal.md`](../../docs/ui-modal.md) — spec du modal Floty (incluant le modal de suppression à deux niveaux).
- [`docs/ui-toast.md`](../../docs/ui-toast.md) — système de toasts (flash Inertia + composable).
- [`docs/layouts.md`](../../docs/layouts.md) — structure des 2 layouts + partials.

## Références

- `project-management/design-system/` — source visuelle à traduire.
- `implementation-rules/vue-composants.md`
- `implementation-rules/assets-vite.md`
- `implementation-rules/tests-frontend.md`
- `implementation-rules/composables-services-utils.md`
- Skill `floty-design-system` (référence visuelle, ne dicte pas la stack)
- Skill `tailwindcss-development` (utilisable pour les classes Tailwind 4)
