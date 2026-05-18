/**
 * Nombre fixe de cellules par année dans la heatmap planning · 53.
 *
 * SC16 (2026-05-18) · convention « cellules » · chaque année Y est
 * représentée par 53 cellules (semaines de 7 jours) couvrant TOUTES les
 * semaines ISO contenant au moins 1 jour de Y · cellule 1 = semaine
 * contenant le 1er janvier, cellule 53 = semaine contenant le 31 décembre.
 *
 * Garantit que les 1-3 jours de fin Y tombant en sem 1 ISO de Y+1 (cas
 * 2024/2025) atterrissent dans la cellule 53 de la heatmap year, et que
 * les 1-3 jours de début Y tombant en sem 53 ISO de Y-1 (cas 2027)
 * atterrissent dans la cellule 1.
 */
export const CELLS_PER_YEAR = 53;

/**
 * Lundi de la cellule 1 de la heatmap year · semaine ISO contenant le
 * 1er janvier (peut être en décembre Y-1).
 */
export function cellOriginForYear(year: number): Date {
    const jan1 = new Date(Date.UTC(year, 0, 1));
    const jsDay = jan1.getUTCDay();
    const isoDay = jsDay === 0 ? 7 : jsDay;
    const monday = new Date(jan1);
    monday.setUTCDate(jan1.getUTCDate() - (isoDay - 1));

    return monday;
}

/**
 * Nombre de semaines ISO dans une année (52 ou 53) · conservé pour
 * usages historiques · préférer {@link CELLS_PER_YEAR} pour la heatmap.
 */
export function isoWeeksInYear(year: number): number {
    return isoWeekNumberOf(new Date(Date.UTC(year, 11, 28)));
}

/**
 * Numéro de semaine ISO 1-53 d'une date.
 */
export function isoWeekNumberOf(date: Date): number {
    const d = new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), date.getUTCDate()));
    const dayNum = d.getUTCDay() || 7;
    // Décale au jeudi de cette semaine ISO (jeudi = 4ᵉ jour)
    d.setUTCDate(d.getUTCDate() + 4 - dayNum);
    const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
    return Math.ceil(((d.getTime() - yearStart.getTime()) / 86_400_000 + 1) / 7);
}
