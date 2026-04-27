/**
 * Convertit une date ISO `YYYY-MM-DD` en format affichable FR `DD/MM/YYYY`.
 *
 * Pas de parsing Date object — on évite les surprises de timezone
 * sur des dates pures (pas d'heure).
 */
export function formatDateFr(iso: string): string {
    const [y, m, d] = iso.split('-');

    return `${d}/${m}/${y}`;
}
