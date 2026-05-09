/**
 * Formate un montant en euros au format français : `1 234,56 €`.
 *
 * Espace fine ` ` (NARROW NO-BREAK SPACE) entre les groupes de
 * milliers et avant le symbole `€`. Cohérent avec le rendu PDF
 * (`BladeDomPdfDeclarationRenderer::formatEuros`) et les conventions
 * typographiques françaises.
 */
export function formatEuros(amount: number): string {
    const formatted = amount.toFixed(2).replace('.', ',');
    const [integer, decimals] = formatted.split(',');
    const grouped = integer.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');

    return `${grouped},${decimals} €`;
}
