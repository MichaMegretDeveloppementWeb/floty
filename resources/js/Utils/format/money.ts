/**
 * Money is stored as integer cents on the backend (`*_cents` columns) and
 * entered/displayed in euros on the frontend. These helpers convert between the
 * two at the form boundary (input in euros → `*_cents` payload, and back when
 * pre-filling an edit form). Display formatting lives in `formatEur`.
 */

/** Euro amount (user input) → integer cents, or null when empty/invalid. */
export function eurosToCents(euros: number | null | undefined): number | null {
    if (euros === null || euros === undefined || Number.isNaN(euros)) {
        return null;
    }

    return Math.round(euros * 100);
}

/** Integer cents (backend) → euro amount for an input/field, or null. */
export function centsToEuros(cents: number | null | undefined): number | null {
    if (cents === null || cents === undefined) {
        return null;
    }

    return cents / 100;
}
