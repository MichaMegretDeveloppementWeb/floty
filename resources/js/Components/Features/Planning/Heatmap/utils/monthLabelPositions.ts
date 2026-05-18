import { CELLS_PER_YEAR, cellOriginForYear } from '@/Utils/Date/isoWeeks';

/**
 * Position d'un label de mois dans la grille heatmap year · les
 * frontières correspondent EXACTEMENT aux transitions de background
 * (au jour près, même au milieu d'une cellule semaine).
 *
 * SC19 (2026-05-18) · les anciens spans en `grid-column: span N`
 * couvraient N cellules entières (selon convention « mois du jeudi »).
 * Avec cette util, le label est positionné en absolute avec left/width
 * calc() · alignement pixel-perfect sur la frontière de mois réelle.
 *
 * Modèle de positionnement · pour un dayOffset D (0..370 dans la grille
 * de CELLS_PER_YEAR * 7 jours) ·
 *   weekIdx = floor(D / 7)
 *   left = D/371 * (100% - 52px) + weekIdx * 1px
 * (100% est la largeur de la grille = 53 cellules + 52 gaps de 1px ;
 *  les 52 gaps fixes sont retirés du calcul % pour ne dépendre que des
 *  largeurs de cellules, puis ajoutés en px à la position selon le
 *  nombre de gaps déjà franchis)
 *
 * Convention pour les mois aux frontières d'année ·
 *   - Mois 1 (janvier) commence à dayOffset 0 (origine grille · peut
 *     être en déc Y-1 mais attribué à janvier de Y)
 *   - Mois 12 (décembre) finit à dayOffset 371 (fin grille · peut être
 *     en jan Y+1 mais attribué à décembre de Y)
 *   - Mois 2-11 · bornes par le 1er du mois et le 1er du mois suivant
 */
export type MonthLabelPosition = {
    monthIdx: number; // 1..12
    /** Offset en jours du début du label dans la grille (0..370). */
    startDayOffset: number;
    /** Offset en jours de la fin du label (1..371). */
    endDayOffset: number;
};

const TOTAL_DAYS = CELLS_PER_YEAR * 7; // 371

export function monthLabelPositionsForYear(year: number): MonthLabelPosition[] {
    const origin = cellOriginForYear(year);
    const positions: MonthLabelPosition[] = [];

    for (let m = 1; m <= 12; m++) {
        // Mois 1 commence forcément à l'origine, mois 12 finit forcément à la fin
        const startDayOffset = m === 1 ? 0 : daysBetween(origin, new Date(Date.UTC(year, m - 1, 1)));
        const endDayOffset = m === 12 ? TOTAL_DAYS : daysBetween(origin, new Date(Date.UTC(year, m, 1)));

        positions.push({ monthIdx: m, startDayOffset, endDayOffset });
    }

    return positions;
}

function daysBetween(a: Date, b: Date): number {
    return Math.round((b.getTime() - a.getTime()) / 86_400_000);
}

/**
 * Expression CSS calc() pour `left` d'une position en dayOffset.
 *
 * Formule · `(D / TOTAL_DAYS) * (100% - 52px) + floor(D / 7) * 1px`
 * où 52 = nombre de gaps fixes dans la grille (CELLS_PER_YEAR - 1).
 */
export function leftCalcForDayOffset(dayOffset: number): string {
    const cellsBefore = Math.floor(dayOffset / 7);
    const fraction = dayOffset / TOTAL_DAYS;

    return `calc(${fraction} * (100% - ${CELLS_PER_YEAR - 1}px) + ${cellsBefore}px)`;
}

/**
 * Expression CSS calc() pour `width` entre 2 dayOffsets.
 *
 * Avec gaps · width = end - start (en pixels).
 * Décompose · fraction de % (largeur des cellules entre) + nombre de
 * gaps complets franchis entre les deux positions.
 */
export function widthCalcBetweenDayOffsets(startDayOffset: number, endDayOffset: number): string {
    const dayDelta = endDayOffset - startDayOffset;
    const gapsBefore = Math.floor(startDayOffset / 7);
    const gapsBeforeEnd = Math.floor(endDayOffset / 7);
    const gapDelta = gapsBeforeEnd - gapsBefore;
    const fraction = dayDelta / TOTAL_DAYS;

    return `calc(${fraction} * (100% - ${CELLS_PER_YEAR - 1}px) + ${gapDelta}px)`;
}
