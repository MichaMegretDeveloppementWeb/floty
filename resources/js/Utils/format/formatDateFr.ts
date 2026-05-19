/**
 * Convert an ISO `YYYY-MM-DD` date string to the French format `DD/MM/YYYY`.
 *
 * No `Date` parsing on purpose: avoids timezone surprises on date-only
 * values.
 */
export function formatDateFr(iso: string): string {
    const [y, m, d] = iso.split('-');

    return `${d}/${m}/${y}`;
}
