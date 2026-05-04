/**
 * Configuration de la table Index Vehicles (server-side, cf. ADR-0020).
 *
 * Particularités :
 *  - Filtres `includeExited` (boolean) + `status` (VehicleStatus | null)
 *  - Colonne `fullYearTax` affichée mais NON triable (valeur calculée
 *    par l'aggregator fiscal — règle ADR-0020 D6)
 *
 * Le rendu reste dans `FleetTable.vue` (slots cell-*).
 */

import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import { useServerTableState } from '@/Composables/Shared/useServerTableState';
import type { ServerTableState } from '@/Composables/Shared/useServerTableState';
import { show as vehiclesShowRoute } from '@/routes/user/vehicles';
import type { DataTableColumn } from '@/types/ui';

type VehicleRow = App.Data.User.Vehicle.VehicleListItemData;

export type FleetSortKey =
    | 'licensePlate'
    | 'model'
    | 'firstFrenchRegistrationDate'
    | 'acquisitionDate'
    | 'currentStatus';

// Mapping clé colonne UI → sortKey backend (whitelist VehicleIndexQueryData).
// La colonne `fullYearTax` n'a pas d'entrée car non triable (D6).
const COLUMN_TO_SORT_KEY: Partial<Record<string, FleetSortKey>> = {
    licensePlate: 'licensePlate',
    model: 'model',
    firstFrenchRegistrationDate: 'firstFrenchRegistrationDate',
};

export type FleetFilters = {
    status: App.Enums.Vehicle.VehicleStatus | null;
    includeExited: boolean;
};

export function useFleetTable(opts: {
    query: App.Data.User.Vehicle.VehicleIndexQueryData;
    fiscalYear: number;
}): {
    columns: readonly DataTableColumn<VehicleRow>[];
    state: ServerTableState<FleetFilters>;
    activeSortColumnKey: ComputedRef<string | null>;
    onHeaderClick: (columnKey: string) => void;
    onRowClick: (row: VehicleRow) => void;
} {
    const columns: readonly DataTableColumn<VehicleRow>[] = [
        { key: 'licensePlate', label: 'Immatriculation' },
        { key: 'model', label: 'Modèle' },
        { key: 'firstFrenchRegistrationDate', label: '1ʳᵉ immat.', mono: true },
        {
            key: 'fullYearTax',
            label: `Coût plein ${opts.fiscalYear}`,
            align: 'right',
        },
    ];

    const state = useServerTableState<FleetFilters>({
        only: ['vehicles', 'query'],
        initialPage: opts.query.page,
        initialPerPage: opts.query.perPage,
        initialSearch: opts.query.search ?? '',
        initialSortKey: opts.query.sortKey,
        initialSortDirection: opts.query.sortDirection,
        defaultFilters: { status: null, includeExited: false },
        initialFilters: {
            status: opts.query.status,
            includeExited: opts.query.includeExited,
        },
        serializeFilters: (f) => ({
            status: f.status,
            // Sérialisation booléenne 1/0/null cohérente avec Spatie Data.
            includeExited: f.includeExited ? 1 : null,
        }),
    });

    const activeSortColumnKey = computed<string | null>(() => {
        if (state.sort.value.key === null) {
            return null;
        }

        const entry = Object.entries(COLUMN_TO_SORT_KEY).find(
            ([, sortKey]) => sortKey === state.sort.value.key,
        );

        return entry ? entry[0] : null;
    });

    function onHeaderClick(columnKey: string): void {
        const sortKey = COLUMN_TO_SORT_KEY[columnKey];

        if (sortKey !== undefined) {
            state.setSort(sortKey);
        }
        // fullYearTax n'a pas de mapping → no-op (header non interactif).
    }

    function onRowClick(row: VehicleRow): void {
        router.visit(vehiclesShowRoute.url({ vehicle: row.id }));
    }

    return {
        columns,
        state,
        activeSortColumnKey,
        onHeaderClick,
        onRowClick,
    };
}
