/**
 * Format a percentage with French convention (`,` decimal, narrow
 * no-break space before `%`).
 *
 * Specific to rental discounts: the percentage is stored as basis
 * points (1 050 bp = 10.50%). Single app-level helper for consistency
 * across badges, tooltips and invoice lines.
 *
 * - `basisPoints = 1050, fractionDigits = 2`    -> `"10,50 %"`
 * - `basisPoints = 1000, fractionDigits = 0`    -> `"10 %"`
 * - `basisPoints = 1050, fractionDigits = 'auto'` -> `"10,5 %"` (drops
 *    trailing zeros)
 *
 * Zero external dependency: `Intl.NumberFormat` covers the FR
 * convention cleanly.
 */
export function formatPercentFromBasisPoints(
    basisPoints: number,
    fractionDigits: number | 'auto' = 'auto',
): string {
    const percent = basisPoints / 100;

    if (fractionDigits === 'auto') {
        return new Intl.NumberFormat('fr-FR', {
            style: 'percent',
            minimumFractionDigits: 0,
            maximumFractionDigits: 2,
        })
            .format(percent / 100)
            .replace(/[  ]/g, ' ');
    }

    return new Intl.NumberFormat('fr-FR', {
        style: 'percent',
        minimumFractionDigits: fractionDigits,
        maximumFractionDigits: fractionDigits,
    })
        .format(percent / 100)
        .replace(/[  ]/g, ' ');
}
