/**
 * Exact pixel positions of each month boundary inside the heatmap grid.
 * Used by the alternating month-parity overlay so transitions land at the
 * correct sub-cell day even when an ISO week straddles two months.
 */

import { isoWeeksInYear } from '@/Utils/date/isoWeeks';
import { HEATMAP_CELL_WIDTH } from './density';

/**
 * A month band in the heatmap grid; startPx inclusive, endPx exclusive.
 * monthIdx is 1-indexed; isOdd memoises parity for alternating colors.
 */
export type MonthBand = {
    monthIdx: number;
    startPx: number;
    endPx: number;
    isOdd: boolean;
};

/** Monday of ISO week 1 of the fiscal year (may physically sit in Dec Y-1). */
function startOfIsoWeek1(year: number): Date {
    // Jan 4 is always in ISO week 1.
    const jan4 = new Date(Date.UTC(year, 0, 4));
    const jsDay = jan4.getUTCDay();
    const isoDay = jsDay === 0 ? 7 : jsDay;
    const monday = new Date(jan4);
    monday.setUTCDate(jan4.getUTCDate() - (isoDay - 1));

    return monday;
}

function diffInDays(from: Date, to: Date): number {
    const MS_PER_DAY = 86_400_000;

    return Math.round((to.getTime() - from.getTime()) / MS_PER_DAY);
}

/**
 * Pixel position of a date within the heatmap grid, given the origin.
 * Each week is CELL_WIDTH + GAP wide; a day inside a week is CELL_WIDTH / 7.
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

/** Compute the 12 month bands for the given fiscal year. */
export function monthBoundariesInPx(
    year: number,
    cellWidthPx: number = HEATMAP_CELL_WIDTH,
    gapPx: number = 1,
): MonthBand[] {
    const origin = startOfIsoWeek1(year);
    const weeksCount = isoWeeksInYear(year);
    const gridTotalPx = weeksCount * cellWidthPx + (weeksCount - 1) * gapPx;

    const positions: number[] = [];

    for (let m = 0; m <= 12; m++) {
        const targetYear = m === 12 ? year + 1 : year;
        const targetMonth = m === 12 ? 0 : m;
        const firstOfMonth = new Date(Date.UTC(targetYear, targetMonth, 1));
        const px = positionInPx(firstOfMonth, origin, cellWidthPx, gapPx);
        positions.push(Math.max(0, Math.min(gridTotalPx, px)));
    }

    const bands: MonthBand[] = [];

    for (let m = 1; m <= 12; m++) {
        bands.push({
            monthIdx: m,
            startPx: positions[m - 1]!,
            endPx: positions[m]!,
            isOdd: m % 2 === 1,
        });
    }

    return bands;
}
