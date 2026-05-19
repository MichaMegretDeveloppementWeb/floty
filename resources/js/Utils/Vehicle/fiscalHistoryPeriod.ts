import { formatDateFr } from '@/Utils/format/formatDateFr';

/**
 * Format the effective period of a vehicle fiscal characteristic for
 * the history timeline (modal opened from the active VFC card).
 *
 * Examples:
 *   - `du 01/01/2024, en cours` (effectiveTo null)
 *   - `du 01/01/2024 au 31/12/2024`
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
