/**
 * Contract duration in days, inclusive on both bounds (start + end).
 *
 * Used by the Contracts Create/Edit live recap.
 *
 * Returns `null` if either date is empty or end < start, so consuming
 * components can display the "to complete" state without a conditional
 * branch in the template.
 *
 * Mirrors `Contract::countDaysInYear` on the backend, which also counts
 * inclusively (start..end included).
 */
export function computeContractDurationDays(
    startDate: string,
    endDate: string,
): number | null {
    if (!startDate || !endDate) {
        return null;
    }

    const start = new Date(startDate);
    const end = new Date(endDate);
    const days = Math.floor((end.getTime() - start.getTime()) / (1000 * 60 * 60 * 24)) + 1;

    return days > 0 ? days : null;
}
