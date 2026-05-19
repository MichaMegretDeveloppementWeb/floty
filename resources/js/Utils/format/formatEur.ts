/**
 * Format a euro amount with French separators and a narrow no-break
 * space (NNBSP, U+202F) before the currency symbol, matching FR
 * typographic conventions.
 *
 * Node's `Intl.NumberFormat` may insert a regular thin space (U+2009)
 * or a non-breaking space (U+00A0); we normalize to NNBSP for visual
 * consistency.
 *
 * @param value          Euro amount.
 * @param fractionDigits Fixed decimals (default 0 for aggregates, 2 for
 *                       detailed amounts).
 */
export function formatEur(value: number, fractionDigits = 0): string {
    return new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency: 'EUR',
        minimumFractionDigits: fractionDigits,
        maximumFractionDigits: fractionDigits,
    })
        .format(value)
        // Normalize NBSP (U+00A0) and THIN SPACE (U+2009) to NNBSP (U+202F).
        .replace(/[  ]/g, ' ');
}
