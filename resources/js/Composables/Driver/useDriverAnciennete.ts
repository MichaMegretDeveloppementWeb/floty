/**
 * Calcul de l'ancienneté d'un conducteur depuis sa première membership
 * (date `joinedAt` la plus ancienne, toutes companies confondues).
 *
 * Helper pur (pas d'effet de bord, pas de réactivité Vue) - testable
 * unitairement et réutilisable depuis n'importe quel composant.
 *
 * Format de retour :
 *   - "-" si aucune membership
 *   - "X an(s)" si > 12 mois pile
 *   - "X mois" si < 12 mois
 *   - "X an Y mois" sinon
 */
type Membership = { joinedAt: string };

/**
 * Calcule l'ancienneté en mois entre deux dates ISO `Y-m-d`.
 * Helper pur, exporté pour test isolé.
 */
export function ancienneteMonths(from: string, today: Date): number {
    const parts = from.split('-').map((s) => parseInt(s, 10));
    const y = parts[0] ?? 1970;
    const m = parts[1] ?? 1;
    const d = parts[2] ?? 1;
    const fromDate = new Date(y, m - 1, d);
    const years = today.getFullYear() - fromDate.getFullYear();
    const months = today.getMonth() - fromDate.getMonth();
    let total = years * 12 + months;

    if (today.getDate() < fromDate.getDate()) {
        total -= 1;
    }

    return Math.max(0, total);
}

/**
 * Formate un nombre de mois en libellé FR lisible.
 */
export function formatAnciennete(months: number): string {
    if (months <= 0) {
        return "Moins d'un mois";
    }

    const years = Math.floor(months / 12);
    const remainingMonths = months % 12;

    if (years === 0) {
        return `${remainingMonths} mois`;
    }

    const yearLabel = years === 1 ? 'an' : 'ans';

    if (remainingMonths === 0) {
        return `${years} ${yearLabel}`;
    }

    return `${years} ${yearLabel} ${remainingMonths} mois`;
}

/**
 * Compose anciennetéMonths + formatAnciennete depuis la liste de
 * memberships d'un driver. Renvoie "-" si aucune membership.
 */
export function useDriverAnciennete(
    memberships: ReadonlyArray<Membership>,
    today: Date = new Date(),
): string {
    if (memberships.length === 0) {
        return '-';
    }

    const sorted = memberships.map((m: Membership) => m.joinedAt).sort();
    const oldestJoinedAt = sorted[0];

    if (oldestJoinedAt === undefined) {
        return '-';
    }

    return formatAnciennete(ancienneteMonths(oldestJoinedAt, today));
}
