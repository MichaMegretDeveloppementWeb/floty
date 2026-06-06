/**
 * Category dedup helpers for the vehicle-event forms (Chantier A, multi
 * categories). Comparison is by trimmed, case-insensitive text · matching the
 * server-side `EventCategoryList` so the frontend prevents what the backend
 * would dedup anyway.
 */

export function normalizeCategory(value: string): string {
    return value.trim().toLowerCase();
}

/**
 * Indices of `custom` entries that duplicate (case-insensitive) a locked
 * default or an earlier custom entry. Empty values are ignored.
 */
export function duplicateCustomIndices(
    custom: ReadonlyArray<string>,
    locked: ReadonlyArray<string> = [],
): Set<number> {
    const seen = new Set(
        locked.map(normalizeCategory).filter((key) => key !== ''),
    );
    const duplicates = new Set<number>();

    custom.forEach((value, index) => {
        const key = normalizeCategory(value);

        if (key === '') {
            return;
        }

        if (seen.has(key)) {
            duplicates.add(index);
        } else {
            seen.add(key);
        }
    });

    return duplicates;
}

export function hasDuplicateCategories(
    custom: ReadonlyArray<string>,
    locked: ReadonlyArray<string> = [],
): boolean {
    return duplicateCustomIndices(custom, locked).size > 0;
}

/** Non-empty, trimmed custom categories (what gets sent to the backend). */
export function cleanCustomCategories(custom: ReadonlyArray<string>): string[] {
    return custom.map((value) => value.trim()).filter((value) => value !== '');
}
