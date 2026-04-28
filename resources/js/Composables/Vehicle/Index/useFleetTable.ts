import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import { show as vehiclesShowRoute } from '@/routes/user/vehicles';
import type { DataTableColumn } from '@/types/ui';

type VehicleRow = App.Data.User.Vehicle.VehicleListItemData;

/**
 * Configuration colonnes + libellés/couleurs de statut + handler
 * de navigation pour le tableau Flotte (page Index véhicules).
 */
export function useFleetTable(props: { fiscalYear: number }): {
    columns: ComputedRef<readonly DataTableColumn<VehicleRow>[]>;
    statusLabel: Record<string, string>;
    statusDotClass: Record<string, string>;
    handleRowClick: (row: VehicleRow) => void;
} {
    const columns = computed<readonly DataTableColumn<VehicleRow>[]>(() => [
        { key: 'licensePlate', label: 'Immatriculation' },
        { key: 'model', label: 'Modèle' },
        { key: 'firstFrenchRegistrationDate', label: '1ʳᵉ immat.', mono: true },
        { key: 'fullYearTax', label: `Coût plein ${props.fiscalYear}`, align: 'right' },
    ]);

    const statusLabel: Record<string, string> = {
        active: 'Active',
        maintenance: 'Maintenance',
        sold: 'Vendu',
        destroyed: 'Détruit',
        other: 'Autre',
    };

    const statusDotClass: Record<string, string> = {
        active: 'bg-emerald-500',
        maintenance: 'bg-amber-500',
        sold: 'bg-slate-400',
        destroyed: 'bg-rose-500',
        other: 'bg-slate-400',
    };

    const handleRowClick = (row: VehicleRow): void => {
        router.visit(vehiclesShowRoute.url({ vehicle: row.id }));
    };

    return { columns, statusLabel, statusDotClass, handleRowClick };
}
