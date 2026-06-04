/**
 * Fixed number of cells per year in the planning heatmap: 53.
 *
 * Each year Y is represented by 53 cells (7-day weeks) covering every
 * ISO week that contains at least one day of Y. Cell 1 = week
 * containing January 1st, cell 53 = week containing December 31st.
 *
 * Guarantees that the 1-3 trailing days of Y landing in ISO week 1 of
 * Y+1 (e.g. 2024/2025) fall in cell 53 of the year heatmap, and that
 * the 1-3 leading days of Y landing in ISO week 53 of Y-1 (e.g. 2027)
 * fall in cell 1.
 */
export const CELLS_PER_YEAR = 53;

/**
 * Fixed pixel width of one heatmap cell.
 *
 * Cells are intentionally a fixed size, independent of viewport width
 * (previously `minmax(14px, 1fr)` shrank cells below a threshold,
 * producing inconsistent UX between large and small screens). With
 * 21px fixed:
 *   - total grid width: 53 x 21 + 52 (gaps) = 1165 px
 *   - large screens (max-w-1600): the central block leaves 79 px of
 *     empty space on the right (visually acceptable, card bg-white)
 *   - small screens: horizontal scroll on the central block only
 *     (overflow-auto already in place); cells stay at 21 px = same
 *     UX as large screens.
 */
export const CELL_WIDTH_PX = 21;

/**
 * Total pixel width of the year heatmap grid (cells + gaps). Used as
 * fixed `width` of the central block's inner wrapper so that
 * `calc(100% - 52px)` of the month labels aligns on the cells area.
 */
export const GRID_CONTENT_WIDTH_PX =
    CELLS_PER_YEAR * CELL_WIDTH_PX + (CELLS_PER_YEAR - 1);

/**
 * Monday of cell 1 in the year heatmap: ISO week containing January 1st
 * (may fall in December Y-1).
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
 * Number of ISO weeks in a year (52 or 53). Kept for legacy callers;
 * prefer {@link CELLS_PER_YEAR} for the heatmap.
 */
export function isoWeeksInYear(year: number): number {
    return isoWeekNumberOf(new Date(Date.UTC(year, 11, 28)));
}

/**
 * ISO week number (1-53) of a date.
 */
export function isoWeekNumberOf(date: Date): number {
    const d = new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), date.getUTCDate()));
    const dayNum = d.getUTCDay() || 7;
    // Shift to the Thursday of that ISO week (Thursday = 4th day).
    d.setUTCDate(d.getUTCDate() + 4 - dayNum);
    const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));

    return Math.ceil(((d.getTime() - yearStart.getTime()) / 86_400_000 + 1) / 7);
}
