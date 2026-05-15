import { formatDateFr } from '@/Utils/format/formatDateFr';

/**
 * Formate la période d'effet d'une caractéristique fiscale véhicule
 * pour la timeline historique (modal ouvert depuis la card actives).
 *
 * Exemples ·
 *   - `du 01/01/2024, en cours` (effectiveTo null)
 *   - `du 01/01/2024 au 31/12/2024`
 *
 * (Lot 7 D02 · F-40-007 · extrait de l'ex-composable
 * `useFiscalHistoryTimeline.ts` qui était un util pur déguisé en
 * composable. Cf. doctrine R10 · `Composables/` héberge des fonctions
 * retournant un reactive state, les utils stateless vont dans `utils/`.)
 */
export function formatFiscalHistoryPeriod(
    item: App.Data.User.Vehicle.VehicleFiscalCharacteristicsData,
): string {
    const from = formatDateFr(item.effectiveFrom);

    if (item.effectiveTo === null) {
        return `du ${from}, en cours`;
    }

    return `du ${from} au ${formatDateFr(item.effectiveTo)}`;
}
