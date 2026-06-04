/**
 * Convert an ISO `YYYY-MM-DD` date string to a long French day label,
 * e.g. `mer. 12 juin 2026`.
 *
 * The date is rebuilt from its components at UTC midnight (no offset
 * parsing, read back in UTC) so the weekday and day never drift across
 * timezones for a date-only value.
 */
const dayFormatter = new Intl.DateTimeFormat('fr-FR', {
    weekday: 'short',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    timeZone: 'UTC',
});

export function formatDayLongFr(iso: string): string {
    const parts = iso.split('-');

    return dayFormatter.format(
        new Date(Date.UTC(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]))),
    );
}
