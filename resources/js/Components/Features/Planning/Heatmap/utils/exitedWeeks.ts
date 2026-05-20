/**
 * Returns the ISO week number (1-52) for a YYYY-MM-DD date.
 * Sufficient for greying out heatmap cells after exit_date; not authoritative for fiscal logic.
 */
export function isoWeekOf(date: string): number {
    const target = new Date(`${date}T00:00:00`);
    const tmp = new Date(Date.UTC(
        target.getFullYear(),
        target.getMonth(),
        target.getDate(),
    ));
    const dayNum = tmp.getUTCDay() || 7;
    tmp.setUTCDate(tmp.getUTCDate() + 4 - dayNum);
    const yearStart = new Date(Date.UTC(tmp.getUTCFullYear(), 0, 1));

    return Math.ceil(
        (((tmp.getTime() - yearStart.getTime()) / 86_400_000) + 1) / 7,
    );
}

/**
 * True iff the cell at the given 0-based week index falls after the
 * vehicle's exit date for the given fiscal year.
 */
export function isCellAfterExit(
    weekIndex: number,
    exitDate: string | null,
    fiscalYear: number,
): boolean {
    if (exitDate === null) {
        return false;
    }

    const exitYear = Number.parseInt(exitDate.slice(0, 4), 10);

    if (exitYear < fiscalYear) {
        return true;
    }

    if (exitYear > fiscalYear) {
        return false;
    }

    return weekIndex + 1 > isoWeekOf(exitDate);
}
