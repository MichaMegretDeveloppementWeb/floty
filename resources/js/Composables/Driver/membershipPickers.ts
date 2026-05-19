/**
 * Pure helpers for the Driver↔Company membership picker modals.
 *
 * Symmetric:
 *   - `filterAvailableCompanies` feeds `AddDriverCompanyModal` on the Driver page.
 *   - `filterAvailableDrivers` feeds `AddCompanyDriverModal` on the Company page.
 *
 * Filtering excludes currently active memberships. A driver may be re-attached to a company
 * they previously left: memberships are temporal, `joined_at` / `left_at` can produce several
 * pivot rows for the same couple.
 */

type CompanyOption = { id: number; shortCode: string; legalName: string };
type DriverOption = { id: number; fullName: string; initials: string };

type SelectOption = { value: number; label: string };

export function filterAvailableCompanies(
    available: ReadonlyArray<CompanyOption>,
    excludedIds: ReadonlyArray<number>,
): SelectOption[] {
    const excluded = new Set(excludedIds);

    return available
        .filter((c) => !excluded.has(c.id))
        .map((c) => ({
            value: c.id,
            label: `${c.shortCode} · ${c.legalName}`,
        }));
}

export function filterAvailableDrivers(
    available: ReadonlyArray<DriverOption>,
    excludedIds: ReadonlyArray<number>,
): SelectOption[] {
    const excluded = new Set(excludedIds);

    return available
        .filter((d) => !excluded.has(d.id))
        .map((d) => ({
            value: d.id,
            label: d.fullName,
        }));
}
