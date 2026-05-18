/**
 * Échelle de densité 0 → 7 (jours utilisés / semaine) calée sur la
 * palette `bg-blue-*` du design system.
 *
 * Extrait du composant `Heatmap` pour réutilisation dans la légende
 * et les cellules sans dupliquer la logique.
 */

export function densityClass(days: number): string {
    if (days <= 0) {
        // SC12 (2026-05-18) · cellule vide bg-white OPAQUE · l'overlay
        // alterné mois pair/impair posé en arrière-plan dans `Heatmap.vue`
        // passe DERRIÈRE les cellules · effet visible dans les gaps 1px
        // entre cellules + 14px de blanc au-dessus/en-dessous des boutons
        // h-7 dans chaque row h-56. L'overlay utilise un flex synchronisé
        // avec les vraies cellules + gradients pour les cellules qui
        // chevauchent 2 mois · transition pixel-exacte au bon endroit.
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
/**
 * Largeur baseline pour une grille à 52 semaines · conservée pour
 * compatibilité, mais préférer un calcul dynamique via
 * {@link isoWeeksInYear} (cf. SC14 · 2026-05-18 · années à 53 semaines
 * comme 2026).
 */
export const HEATMAP_GRID_WIDTH = 52 * HEATMAP_CELL_WIDTH;
