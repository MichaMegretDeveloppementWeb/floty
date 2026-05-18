/**
 * Échelle de densité 0 → 7 (jours utilisés / semaine) calée sur la
 * palette `bg-blue-*` du design system.
 *
 * Extrait du composant `Heatmap` pour réutilisation dans la légende
 * et les cellules sans dupliquer la logique.
 */

export function densityClass(days: number): string {
    if (days <= 0) {
        // SC2 (2026-05-18) · cellule vide volontairement TRANSPARENTE (pas
        // de `bg-white`) pour laisser passer l'overlay alterné mois
        // pair/impair posé en arrière-plan dans `Heatmap.vue` (bandes
        // verticales `bg-slate-100` sur mois impairs). La bordure
        // `border-slate-200` reste pour matérialiser la cellule.
        // Note · les usages secondaires (HeatmapLegend, CompanyActivityCard)
        // sont sur fond blanc parent → cellule reste visuellement
        // identique (transparent sur blanc = blanc).
        return 'border border-slate-200';
    }

    if (days === 1) {
        return 'bg-blue-50';
    }

    if (days === 2) {
        return 'bg-blue-100';
    }

    if (days === 3) {
        return 'bg-blue-300';
    }

    if (days === 4) {
        return 'bg-blue-500';
    }

    if (days === 5) {
        return 'bg-blue-700';
    }

    if (days === 6) {
        return 'bg-blue-800';
    }

    return 'bg-blue-900';
}

export function textContrastClass(days: number): string {
    return days >= 3 ? 'text-white' : 'text-slate-500';
}

/** Constantes de layout - partagées entre l'orchestrateur et les partials. */
export const HEATMAP_CELL_WIDTH = 21;
export const HEATMAP_GRID_WIDTH = 52 * HEATMAP_CELL_WIDTH;
