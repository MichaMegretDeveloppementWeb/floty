/**
 * Calcule les positions pixel EXACTES du 1er de chaque mois civil dans
 * la grille heatmap planning (52 colonnes semaine ISO de l'année).
 *
 * SC6 (2026-05-18) · remplace l'approximation initiale par ratio fixe
 * 4/4/5/4/... (qui ne tombait pas pile sur les frontières de mois). Avec
 * cette util, l'overlay alterné mois pair/impair montre la frontière
 * EXACTEMENT au bon endroit, même si elle tombe au milieu d'une cellule
 * semaine (cas typique · une semaine ISO chevauche souvent 2 mois).
 *
 * Algo · pour chaque mois M de l'année Y, on calcule la position du 1er
 * en jours depuis le LUNDI de la semaine ISO 1 (origine de la grille).
 * Conversion en pixel · `weekIdx × (CELL_WIDTH + GAP) + (dayInWeek / 7) × CELL_WIDTH`.
 *
 * Cas edge · si le 1er janvier est en semaine ISO 52/53 de Y-1 (cas
 * années comme 2023 · 1er janv = dimanche), la position serait négative.
 * On clampe à 0 (la grille démarre au 1er janvier visuel, peu importe
 * qu'il soit physiquement en semaine 52 de Y-1).
 *
 * Symétrie · on calcule aussi la position du 1er janvier de Y+1 pour
 * matérialiser la fin du 12ᵉ mois (= début de janvier suivant, clampé
 * à la fin de la grille si déborde).
 */

import { HEATMAP_CELL_WIDTH } from './density';

/**
 * Une bande de mois dans la grille heatmap · `startPx` inclus, `endPx`
 * exclus. La largeur de la bande = `endPx - startPx`.
 *
 * `monthIdx` est 1-indexé (1 = janvier, 12 = décembre).
 *
 * `isOdd` mémoïse la parité pour piloter l'alternance de couleur
 * (impairs = bg-slate-100, pairs = transparent par défaut).
 */
export type MonthBand = {
    monthIdx: number;
    startPx: number;
    endPx: number;
    isOdd: boolean;
};

/**
 * Origine de la grille heatmap · lundi de la semaine ISO 1 de l'année
 * fiscale (peut être physiquement en décembre Y-1, ex. 29/12/2025 pour
 * Y=2026). Utilisé pour mesurer l'offset en jours du 1er de chaque mois.
 */
function startOfIsoWeek1(year: number): Date {
    // Le 4 janvier est toujours en semaine ISO 1.
    const jan4 = new Date(Date.UTC(year, 0, 4));
    // ISO day of week · 1=lundi, 7=dimanche (JS getUTCDay · 0=dim, 1=lun, ...)
    const jsDay = jan4.getUTCDay();
    const isoDay = jsDay === 0 ? 7 : jsDay;
    // Retourne le lundi de cette semaine (recule de isoDay - 1 jours).
    const monday = new Date(jan4);
    monday.setUTCDate(jan4.getUTCDate() - (isoDay - 1));

    return monday;
}

/**
 * Différence en jours entre 2 dates UTC (entiers, pas de fraction).
 */
function diffInDays(from: Date, to: Date): number {
    const MS_PER_DAY = 86_400_000;

    return Math.round((to.getTime() - from.getTime()) / MS_PER_DAY);
}

/**
 * Position pixel d'une date dans la grille heatmap, à partir de l'origine
 * (lundi semaine 1 ISO de l'année). Modèle · chaque semaine occupe
 * `CELL_WIDTH + GAP` px (la cellule semaine + le gap qui la sépare de
 * la suivante), et un jour dans une semaine occupe `CELL_WIDTH / 7` px.
 *
 * Le résultat peut être négatif si la date est avant l'origine (cas
 * rare · 1er janvier en semaine 52/53 de Y-1) · l'appelant clampe.
 */
function positionInPx(
    target: Date,
    origin: Date,
    cellWidthPx: number,
    gapPx: number,
): number {
    const daysFromOrigin = diffInDays(origin, target);
    const weekIdx = Math.floor(daysFromOrigin / 7);
    const dayInWeek = daysFromOrigin - weekIdx * 7;

    return weekIdx * (cellWidthPx + gapPx) + (dayInWeek / 7) * cellWidthPx;
}

/**
 * Calcule les 12 bandes mensuelles de l'année fiscale Y.
 *
 * @param year  année fiscale (1-2099)
 * @param cellWidthPx  largeur d'une cellule semaine (défaut HEATMAP_CELL_WIDTH = 21)
 * @param gapPx  gap entre 2 cellules semaine (défaut 1)
 * @returns 12 bandes ordonnées de janvier à décembre · `startPx` du mois
 *          M = `endPx` du mois M-1 (sauf le 1er janvier clampé à 0).
 *          `endPx` du 12 = position du 1er janvier de Y+1 (clampée à la
 *          largeur totale de la grille si déborde).
 */
export function monthBoundariesInPx(
    year: number,
    cellWidthPx: number = HEATMAP_CELL_WIDTH,
    gapPx: number = 1,
): MonthBand[] {
    const origin = startOfIsoWeek1(year);
    const gridTotalPx = 52 * cellWidthPx + 51 * gapPx;

    const positions: number[] = [];
    for (let m = 0; m <= 12; m++) {
        // m=0..11 → 1er du mois m+1 de l'année Y
        // m=12 → 1er janvier de l'année Y+1 (fin du 12ᵉ mois)
        const targetYear = m === 12 ? year + 1 : year;
        const targetMonth = m === 12 ? 0 : m;
        const firstOfMonth = new Date(Date.UTC(targetYear, targetMonth, 1));
        const px = positionInPx(firstOfMonth, origin, cellWidthPx, gapPx);
        // Clamp · ne sort pas de la grille [0, gridTotalPx]
        positions.push(Math.max(0, Math.min(gridTotalPx, px)));
    }

    const bands: MonthBand[] = [];
    for (let m = 1; m <= 12; m++) {
        bands.push({
            monthIdx: m,
            startPx: positions[m - 1]!,
            endPx: positions[m]!,
            // mois impairs (1, 3, 5, 7, 9, 11) → bande de couleur
            isOdd: m % 2 === 1,
        });
    }

    return bands;
}
