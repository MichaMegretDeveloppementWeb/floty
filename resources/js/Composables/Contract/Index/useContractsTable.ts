import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import { show as contractsShowRoute } from '@/routes/user/contracts';
import type { DataTableColumn } from '@/types/ui';
import { contractTypeLabel } from '@/Utils/labels/contractEnumLabels';

type ContractRow = App.Data.User.Contract.ContractListItemData;

/**
 * Configuration colonnes + handler de navigation pour la table de la
 * page Index Contracts.
 */
export function useContractsTable(): {
    columns: ComputedRef<readonly DataTableColumn<ContractRow>[]>;
    typeLabel: typeof contractTypeLabel;
    handleRowClick: (row: ContractRow) => void;
} {
    const columns = computed<readonly DataTableColumn<ContractRow>[]>(() => [
        { key: 'vehicleLicensePlate', label: 'Véhicule' },
        { key: 'companyShortCode', label: 'Entreprise' },
        { key: 'startDate', label: 'Du', mono: true },
        { key: 'endDate', label: 'Au', mono: true },
        { key: 'durationDays', label: 'Durée', align: 'right', mono: true },
        { key: 'contractType', label: 'Type' },
    ]);

    const handleRowClick = (row: ContractRow): void => {
        router.visit(contractsShowRoute.url({ contract: row.id }));
    };

    return { columns, typeLabel: contractTypeLabel, handleRowClick };
}
