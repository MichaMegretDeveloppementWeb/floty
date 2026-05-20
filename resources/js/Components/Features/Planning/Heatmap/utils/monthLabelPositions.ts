import { CELLS_PER_YEAR, cellOriginForYear } from '@/Utils/Date/isoWeeks';

/**
 * Pixel-aware month label position in the heatmap grid.
 * Positions are expressed as day offsets so transitions land on the actual
 * month boundary, even mid-week.
 */
export type MonthLabelPosition = {
    monthIdx: number; // 1..12
    /** Day offset of the label start within the grid (0..370). */
    startDayOffset: number;
    /** Day offset of the label end (1..371). */
    endDayOffset: number;
};

const TOTAL_DAYS = CELLS_PER_YEAR * 7; // 371

export function monthLabelPositionsForYear(year: number): MonthLabelPosition[] {
    const origin = cellOriginForYear(year);
    const positions: MonthLabelPosition[] = [];

    for (let m = 1; m <= 12; m++) {
        // Month 1 always starts at the grid origin; month 12 always ends at the grid edge.
        const startDayOffset = m === 1 ? 0 : daysBetween(origin, new Date(Date.UTC(year, m - 1, 1)));
        const endDayOffset = m === 12 ? TOTAL_DAYS : daysBetween(origin, new Date(Date.UTC(year, m, 1)));

        positions.push({ monthIdx: m, startDayOffset, endDayOffset });
    }

    return positions;
}

function daysBetween(a: Date, b: Date): number {
    return Math.round((b.getTime() - a.getTime()) / 86_400_000);
}

/** CSS calc() expression for the `left` of a day-offset position. */
export function leftCalcForDayOffset(dayOffset: number): string {
    const cellsBefore = Math.floor(dayOffset / 7);
    const fraction = dayOffset / TOTAL_DAYS;

    return `calc(${fraction} * (100% - ${CELLS_PER_YEAR - 1}px) + ${cellsBefore}px)`;
}

/** CSS calc() expression for the `width` between two day-offset positions. */
export function widthCalcBetweenDayOffsets(startDayOffset: number, endDayOffset: number): string {
    const dayDelta = endDayOffset - startDayOffset;
    const gapsBefore = Math.floor(startDayOffset / 7);
    const gapsBeforeEnd = Math.floor(endDayOffset / 7);
    const gapDelta = gapsBeforeEnd - gapsBefore;
    const fraction = dayDelta / TOTAL_DAYS;

    return `calc(${fraction} * (100% - ${CELLS_PER_YEAR - 1}px) + ${gapDelta}px)`;
}
