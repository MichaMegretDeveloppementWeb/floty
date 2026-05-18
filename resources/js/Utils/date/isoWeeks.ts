/**
 * Nombre de semaines ISO dans une année (52 ou 53).
 *
 * SC14 (2026-05-18) · une année a 53 semaines ISO si ·
 *   - le 1er janvier est un jeudi (cas 2026), OU
 *   - l'année est bissextile et le 1er janvier est un mercredi (cas 2020)
 *
 * Détection rigoureuse via la convention "le 28 décembre est toujours
 * dans la dernière semaine ISO de l'année" · isoWeek(28/12/year) retourne
 * 52 ou 53.
 */
export function isoWeeksInYear(year: number): number {
    // Le 28 décembre est toujours dans la dernière semaine ISO
    return isoWeekNumberOf(new Date(Date.UTC(year, 11, 28)));
}

/**
 * Numéro de semaine ISO 1-53 d'une date.
 */
export function isoWeekNumberOf(date: Date): number {
    const d = new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), date.getUTCDate()));
    const dayNum = d.getUTCDay() || 7;
    // Décale au jeudi de cette semaine ISO (jeudi = 4ᵉ jour)
    d.setUTCDate(d.getUTCDate() + 4 - dayNum);
    const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
    return Math.ceil(((d.getTime() - yearStart.getTime()) / 86_400_000 + 1) / 7);
}
