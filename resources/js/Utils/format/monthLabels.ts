/**
 * Long French civil month labels, indexed 0-11 (January = index 0).
 * Centralizes the 4 historical duplications from the Billing/Invoice
 * components.
 *
 * For short labels (initial or 3-letter), see the dedicated variants in
 * `CompanyActivityCard.vue` and `Heatmap.vue` (visualization-specific
 * formats, out of scope here).
 */
export const MONTH_LABELS = [
    'Janvier',
    'Février',
    'Mars',
    'Avril',
    'Mai',
    'Juin',
    'Juillet',
    'Août',
    'Septembre',
    'Octobre',
    'Novembre',
    'Décembre',
] as const;

export type MonthLabel = (typeof MONTH_LABELS)[number];

/**
 * Returns the long French label for a civil month (1-12). Throws
 * `RangeError` if `month` is outside `[1, 12]`.
 */
export function monthLabel(month: number): MonthLabel {
    if (month < 1 || month > 12) {
        throw new RangeError(`Month must be in [1, 12], got ${month}.`);
    }

    // Bounded by the [1, 12] guard above, index is always valid.
    return MONTH_LABELS[month - 1]!;
}
