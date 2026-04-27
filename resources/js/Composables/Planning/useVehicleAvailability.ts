import { ref } from 'vue';
import type { Ref } from 'vue';
import { useApi } from '@/Composables/Shared/useApi';
import { vehicleDates as vehicleDatesRoute } from '@/routes/user/assignments';

/**
 * Charge et expose les dates occupées d'un véhicule (toutes
 * entreprises) + le map `companyId → dates` du couple courant.
 *
 * Les dates occupées doivent griser le calendrier ; les pair dates
 * du couple sélectionné doivent être affichées dans un état
 * « existant » plutôt que désactivé.
 */
export type UseVehicleAvailabilityReturn = {
    busyDates: Ref<string[]>;
    pairDatesByCompany: Ref<Record<string, string[]>>;
    /** Charge les dates pour un véhicule + année fiscale. */
    load: (vehicleId: number, year: number) => Promise<void>;
    /** Dates du couple (vehicleId courant, companyId fourni). */
    pairDatesFor: (companyId: number | null) => string[];
    reset: () => void;
};

export function useVehicleAvailability(): UseVehicleAvailabilityReturn {
    const api = useApi();
    const busyDates = ref<string[]>([]);
    const pairDatesByCompany = ref<Record<string, string[]>>({});

    const reset = (): void => {
        busyDates.value = [];
        pairDatesByCompany.value = {};
    };

    const load = async (vehicleId: number, year: number): Promise<void> => {
        try {
            const data = await api.get<App.Data.User.Assignment.VehicleDatesData>(
                vehicleDatesRoute.url(),
                { vehicleId, year },
            );
            busyDates.value = data.vehicleBusyDates;
            pairDatesByCompany.value = data.pairDates;
        } catch {
            reset();
        }
    };

    const pairDatesFor = (companyId: number | null): string[] => {
        if (companyId === null) {
            return [];
        }

        return pairDatesByCompany.value[String(companyId)] ?? [];
    };

    return { busyDates, pairDatesByCompany, load, pairDatesFor, reset };
}
