/**
 * Helpers purs pour les pickers des modaux de membership Driver↔Company.
 *
 * Symétriques :
 *   - `filterAvailableCompanies` alimente `AddDriverCompanyModal` côté
 *     fiche Driver (l'utilisateur a un driver fixe, choisit une company).
 *   - `filterAvailableDrivers` alimente `AddCompanyDriverModal` côté
 *     fiche Company (l'utilisateur a une company fixe, choisit un driver).
 *
 * Le filtrage exclut les memberships **actuellement actives** (un même
 * driver peut être ré-attaché à une company qu'il a quittée, cf. option A
 * du compte rendu d'audit chantier M : une membership est temporelle,
 * `joined_at` / `left_at` peuvent générer plusieurs lignes pivot pour
 * le même couple).
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
