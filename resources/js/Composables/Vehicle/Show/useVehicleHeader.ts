import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import type { StatusTone } from '@/types/ui';
import { formatDateFr } from '@/Utils/format/formatDateFr';

/**
 * Logique d'affichage du header véhicule (page Show) : badge de
 * statut + ligne d'infos secondaires (acquisition, immat., VIN,
 * couleur, kilométrage).
 */
export function useVehicleHeader(props: {
    vehicle: App.Data.User.Vehicle.VehicleData;
}): {
    statusTone: Record<App.Enums.Vehicle.VehicleStatus, StatusTone>;
    secondaryInfo: ComputedRef<string[]>;
} {
    const statusTone: Record<App.Enums.Vehicle.VehicleStatus, StatusTone> = {
        active: 'emerald',
        maintenance: 'amber',
        sold: 'slate',
        destroyed: 'rose',
        other: 'slate',
    };

    const secondaryInfo = computed<string[]>(() => {
        const v = props.vehicle;
        const parts: string[] = [
            `Acquis le ${formatDateFr(v.acquisitionDate)}`,
            `1ʳᵉ immat. FR ${formatDateFr(v.firstFrenchRegistrationDate)}`,
        ];

        if (v.vin) {
            parts.push(`VIN ${v.vin}`);
        }

        if (v.color) {
            parts.push(v.color);
        }

        if (v.mileageCurrent !== null) {
            parts.push(`${v.mileageCurrent.toLocaleString('fr-FR')} km`);
        }

        return parts;
    });

    return { statusTone, secondaryInfo };
}
