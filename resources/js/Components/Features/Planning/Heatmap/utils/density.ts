/** Density scale 0..7 (days used per week) mapped to the bg-blue-* palette. */

export function densityClass(days: number): string {
    if (days <= 0) {
        // Empty cell: opaque white so the month-parity overlay shows only in the gaps.
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

/** Inset blue ring for low-density cells (1-2 days). */
export function densityRingClass(days: number): string {
    if (days >= 1 && days <= 2) {
        return 'ring-1 ring-blue-200 ring-inset';
    }

    return '';
}

/** Layout constants shared with the partials. */
export const HEATMAP_CELL_WIDTH = 21;
/** Baseline grid width for 52 weeks; prefer the dynamic isoWeeksInYear helper. */
export const HEATMAP_GRID_WIDTH = 52 * HEATMAP_CELL_WIDTH;
