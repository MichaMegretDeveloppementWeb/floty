/**
 * Number of days in a Gregorian year.
 *
 * Used by every front-end fiscal proration computation (CO2 + pollutant
 * taxes). Twin source of `App\Services\Shared\Fiscal\FiscalYearContext::daysInYear`
 * on the backend: both MUST return the same value for a given year, or
 * the live preview will diverge from the server result.
 *
 * @param year Gregorian year (e.g. 2024).
 * @returns 366 if leap year, 365 otherwise.
 */
export function daysInYear(year: number): 365 | 366 {
    return (year % 4 === 0 && year % 100 !== 0) || year % 400 === 0 ? 366 : 365;
}
