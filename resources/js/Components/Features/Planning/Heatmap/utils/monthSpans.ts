import { CELLS_PER_YEAR, cellOriginForYear } from '@/Utils/Date/isoWeeks';

/**
 * Span d'un mois dans la heatmap year · nombre de cellules dont le
 * mois "majoritaire" est ce mois.
 *
 * SC17 (2026-05-18) · convention « mois du jeudi » (équivalent au mois
 * où ≥ 4 jours sur 7 tombent · norme ISO 8601). Exceptions ·
 *   - cellules dont le jeudi est en Y-1 → forcées à janvier de Y
 *     (cas typique : cell 1 d'années comme 2027 dont sem ISO 1 ne
 *     commence que le 4/1)
 *   - cellules dont le jeudi est en Y+1 → forcées à décembre de Y
 *     (cas typique : cell 53 d'années comme 2024/2025 dont la dernière
 *     semaine contenant 30-31 déc est techniquement sem 1 ISO de Y+1)
 *
 * Cohérent avec la règle d'ouverture du drawer (SC15) · le mois affiché
 * dans le header heatmap est exactement le mois sur lequel le drawer
 * s'ouvre quand on clique sur la cellule.
 *
 * Garantie · Σ spans = CELLS_PER_YEAR (53) · les 12 mois couvrent
 * exactement toutes les cellules sans chevauchement.
 */
export type MonthSpan = {
    monthIdx: number; // 1..12
    span: number; // nombre de cellules
};

export function monthSpansForYear(year: number): MonthSpan[] {
    const origin = cellOriginForYear(year);
    const spans: number[] = new Array(13).fill(0); // index 1..12

    for (let cellIdx = 1; cellIdx <= CELLS_PER_YEAR; cellIdx++) {
        const monday = new Date(origin);
        monday.setUTCDate(origin.getUTCDate() + (cellIdx - 1) * 7);
        const thursday = new Date(monday);
        thursday.setUTCDate(monday.getUTCDate() + 3);
        const thursdayYear = thursday.getUTCFullYear();

        let month: number;
        if (thursdayYear < year) {
            month = 1; // Cellule limite début · jeudi en Y-1 → janvier de Y
        } else if (thursdayYear > year) {
            month = 12; // Cellule limite fin · jeudi en Y+1 → décembre de Y
        } else {
            month = thursday.getUTCMonth() + 1; // Cas normal · mois du jeudi
        }

        spans[month]!++;
    }

    return spans.slice(1).map((span, idx) => ({ monthIdx: idx + 1, span }));
}
