/**
 * Configuration de la table Index Drivers (server-side, cf. ADR-0020).
 *
 * Le composable assemble :
 *  - les colonnes (config visuelle + mapping vers sortKey backend)
 *  - le composable générique `useServerTableState` (orchestration reload)
 *  - le handler de clic sur ligne
 *
 * Le rendu (badges, slots `cell-*`) reste dans `DriversTable.vue`. Le
 * composant page (`Index.vue`) consomme ce composable + binde
 * `Paginator` et `SearchInput` via les setters exposés.
 */

import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import { useServerTableState } from '@/Composables/Shared/useServerTableState';
import type { ServerTableState } from '@/Composables/Shared/useServerTableState';
import { show as showRoute } from '@/routes/user/drivers';
import type { DataTableColumn } from '@/types/ui';

type DriverRow = App.Data.User.Driver.DriverListItemData;

export type DriverSortKey =
    | 'fullName'
    | 'contractsCount'
    | 'activeCompaniesCount';

// Mapping colonne UI → sortKey backend (whitelist DriverIndexQueryData).
const COLUMN_TO_SORT_KEY: Partial<Record<string, DriverSortKey>> = {
    driver: 'fullName',
    companies: 'activeCompaniesCount',
    contractsCount: 'contractsCount',
};

// Pas de filtres spécifiques au domaine Drivers en V1.1 — search uniquement.
type DriverFilters = Record<string, never>;

export function useDriversTable(
    query: App.Data.User.Driver.DriverIndexQueryData,
): {
    columns: readonly DataTableColumn<DriverRow>[];
    state: ServerTableState<DriverFilters>;
    activeSortColumnKey: ComputedRef<string | null>;
    onHeaderClick: (columnKey: string) => void;
    onRowClick: (row: DriverRow) => void;
} {
    const columns: readonly DataTableColumn<DriverRow>[] = [
        { key: 'driver', label: 'Conducteur' },
        { key: 'companies', label: 'Entreprises' },
        { key: 'contractsCount', label: 'Contrats', mono: true },
    ];

    const state = useServerTableState<DriverFilters>({
        only: ['drivers', 'query'],
        initialPage: query.page,
        initialPerPage: query.perPage,
        initialSearch: query.search ?? '',
        initialSortKey: query.sortKey,
        initialSortDirection: query.sortDirection,
        defaultFilters: {},
        serializeFilters: () => ({}),
    });

    // Reverse map sortKey backend → key colonne UI (pour mettre en avant
    // le bon header quand on hydrate depuis l'URL).
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
    }

    function onRowClick(row: DriverRow): void {
        router.visit(showRoute(row.id).url);
    }

    return {
        columns,
        state,
        activeSortColumnKey,
        onHeaderClick,
        onRowClick,
    };
}
