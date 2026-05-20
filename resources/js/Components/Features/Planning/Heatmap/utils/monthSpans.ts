import { CELLS_PER_YEAR, cellOriginForYear } from '@/Utils/Date/isoWeeks';

/**
 * Span of each month in the heatmap year using the "Thursday-month" ISO
 * convention. The 12 spans always sum to CELLS_PER_YEAR.
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
            month = 1;
        } else if (thursdayYear > year) {
            month = 12;
        } else {
            month = thursday.getUTCMonth() + 1;
        }

        spans[month]!++;
    }

    return spans.slice(1).map((span, idx) => ({ monthIdx: idx + 1, span }));
}
