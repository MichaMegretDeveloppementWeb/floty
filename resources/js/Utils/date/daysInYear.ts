/**
 * Nombre de jours dans une année grégorienne donnée.
 *
 * Utilisé pour tous les calculs de prorata fiscaux côté front (taxes
 * CO₂ et polluants). Source jumelle de
 * `App\Services\Shared\Fiscal\FiscalYearContext::daysInYear` côté
 * backend - les deux DOIVENT renvoyer le même résultat pour une année
 * donnée, sinon l'aperçu temps réel diverge du calcul serveur.
 *
 * @param year année grégorienne (ex. 2024)
 * @returns 366 si bissextile, 365 sinon
 */
export function daysInYear(year: number): 365 | 366 {
    return (year % 4 === 0 && year % 100 !== 0) || year % 400 === 0 ? 366 : 365;
}
