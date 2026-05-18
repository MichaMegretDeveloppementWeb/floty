/**
 * Formate un pourcentage en convention FR (`,` décimal, espace insécable
 * fin avant le `%`).
 *
 * Spécifique réductions commerciales (Lot 2/3) · le pourcentage est
 * stocké en basis points (1 050 bp = 10,50 %). Helper applicatif unique
 * pour rester cohérent partout (badges, tooltips, lignes facture).
 *
 * - `basisPoints = 1050, fractionDigits = 2` → `"10,50 %"`
 * - `basisPoints = 1000, fractionDigits = 0` → `"10 %"`
 * - `basisPoints = 1050, fractionDigits = 'auto'` → `"10,5 %"` (élide les
 *    zéros terminaux)
 *
 * Aucune dépendance externe · `Intl.NumberFormat` couvre la convention
 * FR proprement.
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
