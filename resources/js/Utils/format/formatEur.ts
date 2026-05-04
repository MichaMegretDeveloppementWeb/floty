/**
 * Formate un montant en euros avec le séparateur français et un
 * espace insécable fin (NNBSP, U+202F) entre les milliers et le
 * symbole - comme l'attendent les conventions typographiques FR.
 *
 * Le `Intl.NumberFormat` de Node insère parfois un espace fine
 * normale (U+2009) ou un espace insécable (U+00A0). On normalise
 * en NNBSP par cohérence visuelle.
 *
 * @param value          Montant numérique (€)
 * @param fractionDigits Décimales fixes (par défaut 0 pour les
 *                       agrégats, 2 pour les montants détaillés)
 */
export function formatEur(value: number, fractionDigits = 0): string {
    return new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency: 'EUR',
        minimumFractionDigits: fractionDigits,
        maximumFractionDigits: fractionDigits,
    })
        .format(value)
        // Normalise NBSP (U+00A0) et THIN SPACE (U+2009) en NNBSP (U+202F).
        .replace(/[  ]/g, ' ');
}
