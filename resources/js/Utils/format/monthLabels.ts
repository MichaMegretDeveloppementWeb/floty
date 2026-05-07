/**
 * Libellés des mois civils français en format long, indexés 0-11
 * (Janvier = index 0). Centralise les 4 duplications historiques
 * (T11 / E.17) issues des composants Billing/Invoice.
 *
 * Pour des libellés courts (initiale ou 3-lettres), voir les
 * variantes dédiées dans `CompanyActivityCard.vue` et `Heatmap.vue`
 * (formats spécifiques visualisations · hors scope mutualisation).
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
 * Helper : retourne le libellé du mois civil (1-12) au format long FR.
 * Lance une `RangeError` si `month` est hors `[1, 12]`.
 */
export function monthLabel(month: number): MonthLabel {
    if (month < 1 || month > 12) {
        throw new RangeError(`Month must be in [1, 12], got ${month}.`);
    }

    return MONTH_LABELS[month - 1];
}
