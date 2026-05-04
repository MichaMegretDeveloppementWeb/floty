/**
 * Calcule le numéro de semaine ISO (1-52) auquel correspond une date
 * `YYYY-MM-DD`. On approxime avec la convention française ISO 8601 :
 * la semaine 1 contient le 4 janvier.
 *
 * Suffisant pour griser les cellules « après exit_date » dans la
 * heatmap - pas pour de la fiscalité (où le backend reste autorité).
 */
export function isoWeekOf(date: string): number {
    const target = new Date(`${date}T00:00:00`);
    const tmp = new Date(Date.UTC(
        target.getFullYear(),
        target.getMonth(),
        target.getDate(),
    ));
    const dayNum = tmp.getUTCDay() || 7;
    tmp.setUTCDate(tmp.getUTCDate() + 4 - dayNum);
    const yearStart = new Date(Date.UTC(tmp.getUTCFullYear(), 0, 1));

    return Math.ceil(
        (((tmp.getTime() - yearStart.getTime()) / 86_400_000) + 1) / 7,
    );
}

/**
 * Indique si la cellule de la semaine `weekIndex` (0-based, donc
 * semaine ISO `weekIndex + 1`) est après la sortie de flotte du
 * véhicule pour l'année fiscale considérée.
 *
 * Cas :
 *   - `exitDate === null` → false (jamais grisé)
 *   - `exitDate` antérieure au 1er janvier de `fiscalYear` → toutes
 *     grisées (le véhicule ne devrait pas apparaître dans cette
 *     heatmap, mais filet de sécurité)
 *   - `exitDate` postérieure au 31 décembre de `fiscalYear` → aucune
 *     grisée (le véhicule est encore actif sur toute l'année)
 *   - sinon : grise les semaines strictement après celle de exitDate
 */
export function isCellAfterExit(
    weekIndex: number,
    exitDate: string | null,
    fiscalYear: number,
): boolean {
    if (exitDate === null) {
        return false;
    }

    const exitYear = Number.parseInt(exitDate.slice(0, 4), 10);

    if (exitYear < fiscalYear) {
        return true;
    }

    if (exitYear > fiscalYear) {
        return false;
    }

    return weekIndex + 1 > isoWeekOf(exitDate);
}
