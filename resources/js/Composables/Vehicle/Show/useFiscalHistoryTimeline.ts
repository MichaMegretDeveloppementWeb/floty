import { formatDateFr } from '@/Utils/format/formatDateFr';

/**
 * Helpers de formatage pour la timeline historique des
 * caractéristiques fiscales (modal ouvert depuis la card actives).
 */
export function useFiscalHistoryTimeline(): {
    formatPeriod: (
        item: App.Data.User.Vehicle.VehicleFiscalCharacteristicsData,
    ) => string;
} {
    const formatPeriod = (
        item: App.Data.User.Vehicle.VehicleFiscalCharacteristicsData,
    ): string => {
        const from = formatDateFr(item.effectiveFrom);

        if (item.effectiveTo === null) {
            return `du ${from} — en cours`;
        }

        return `du ${from} au ${formatDateFr(item.effectiveTo)}`;
    };

    return { formatPeriod };
}
