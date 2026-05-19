/**
 * Pure helpers for detecting divergence on a monthly billing breakdown
 * entry. An entry is divergent when the snapshot of the emitted invoice
 * differs from the current recompute on either:
 *   - days used, or
 *   - HT total in cents.
 */

import { formatEur } from '@/Utils/format/formatEur';

type Entry = App.Data.User.Billing.MonthlyBillingBreakdownData['entries'][number];

/**
 * True if `entry` corresponds to an emitted invoice whose snapshot
 * (days / total) differs from the current recompute. Returns false if
 * no invoice is emitted or if the snapshot is complete and equal.
 */
export function entryHasDivergence(entry: Entry): boolean {
    if (entry.existingInvoiceId === null || entry.invoicedDaysUsed === null) {
        return false;
    }

    if (entry.daysUsed !== entry.invoicedDaysUsed) {
        return true;
    }

    return (
        entry.totalCents !== null
        && entry.invoicedTotalCents !== null
        && entry.totalCents !== entry.invoicedTotalCents
    );
}

/**
 * Tooltip explanation shown when `entryHasDivergence(entry) === true`.
 * Builds a fixed FR message contrasting snapshot vs recompute.
 */
export function divergenceTooltip(entry: Entry): string {
    const invoicedDays = entry.invoicedDaysUsed ?? 0;
    const invoicedTotal = formatEur((entry.invoicedTotalCents ?? 0) / 100, 2);
    const currentTotal = formatEur((entry.totalCents ?? 0) / 100, 2);

    return (
        `Données obsolètes. Facture émise sur ${invoicedDays} j / ${invoicedTotal}. `
        + `Recalcul actuel : ${entry.daysUsed} j / ${currentTotal}. `
        + 'Régénérez pour mettre à jour avec les données actuelles.'
    );
}
