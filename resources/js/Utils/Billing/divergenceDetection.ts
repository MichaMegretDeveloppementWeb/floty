/**
 * Helpers purs pour la détection de divergence sur une entrée de récap
 * mensuel facturation (T11 / E.3). Extraits de
 * `MonthlyBillingBreakdownCard.vue` pour testabilité Vitest et
 * réutilisabilité éventuelle.
 *
 * Une entrée est divergente si la facture émise (snapshot) diffère
 * du recalcul actuel sur :
 *   - les jours utilisés, ou
 *   - le total HT en cents.
 */

import { formatEur } from '@/Utils/format/formatEur';

type Entry = App.Data.User.Billing.MonthlyBillingBreakdownData['entries'][number];

/**
 * `true` si `entry` correspond à une facture émise dont le snapshot
 * (jours / total) diffère du recalcul actuel. Retourne `false` si
 * aucune facture n'est émise ou si le snapshot est complet et égal.
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
 * Tooltip explicatif côté UI quand `entryHasDivergence(entry) === true`.
 * Compose un message FR fixe avec snapshot vs recalcul.
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
