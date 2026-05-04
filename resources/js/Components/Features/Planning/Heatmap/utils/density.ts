/**
 * Échelle de densité 0 → 7 (jours utilisés / semaine) calée sur la
 * palette `bg-blue-*` du design system.
 *
 * Extrait du composant `Heatmap` pour réutilisation dans la légende
 * et les cellules sans dupliquer la logique.
 */

export function densityClass(days: number): string {
    if (days <= 0) {
        return 'bg-white border border-slate-200';
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
