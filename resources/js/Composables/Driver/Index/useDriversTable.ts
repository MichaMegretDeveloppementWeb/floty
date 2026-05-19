/**
 * Server-side Index table for Drivers (ADR-0020).
 *
 * Assembles columns (visual config + backend sortKey mapping), the generic `useServerTableState`
 * (reload orchestration), and the row click handler.
 *
 * Filters (see DriverIndexQueryData):
 *  - companyId: drivers of a company (all memberships, active or past)
 *  - activityStatus: 'active' = at least one open membership; 'inactive' = none
 *  - contractsScope: 'with' / 'without' contracts
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

// UI column → backend sortKey (DriverIndexQueryData whitelist).
const COLUMN_TO_SORT_KEY: Partial<Record<string, DriverSortKey>> = {
    driver: 'fullName',
    companies: 'activeCompaniesCount',
    contractsCount: 'contractsCount',
};

export type DriverFilters = {
    companyId: number | null;
    activityStatus: 'active' | 'inactive' | null;
    contractsScope: 'with' | 'without' | null;
};

export function useDriversTable(
    query: App.Data.User.Driver.DriverIndexQueryData,
): {
    columns: readonly DataTableColumn<DriverRow>[];
    state: ServerTableState<DriverFilters>;
    activeSortColumnKey: ComputedRef<string | null>;
    activeFiltersCount: ComputedRef<number>;
    onHeaderClick: (columnKey: string) => void;
    onRowClick: (row: DriverRow) => void;
} {
    const columns: readonly DataTableColumn<DriverRow>[] = [
        { key: 'driver', label: 'Conducteur' },
        { key: 'companies', label: 'Entreprises' },
        { key: 'contractsCount', label: 'Locations', mono: true },
    ];

    const state = useServerTableState<DriverFilters>({
        only: ['drivers', 'query'],
        initialPage: query.page,
        initialPerPage: query.perPage,
        initialSearch: query.search ?? '',
        initialSortKey: query.sortKey,
        initialSortDirection: query.sortDirection,
        defaultFilters: {
            companyId: null,
            activityStatus: null,
            contractsScope: null,
        },
        initialFilters: {
            companyId: query.companyId,
            activityStatus: query.activityStatus as
                | 'active'
                | 'inactive'
                | null,
            contractsScope: query.contractsScope as 'with' | 'without' | null,
        },
        serializeFilters: (f) => ({
            companyId: f.companyId,
            activityStatus: f.activityStatus,
            contractsScope: f.contractsScope,
        }),
    });

    // Reverse map backend sortKey → UI column key (highlights the right header when hydrating from URL).
    const activeSortColumnKey = computed<string | null>(() => {
        if (state.sort.value.key === null) {
            return null;
        }

        const entry = Object.entries(COLUMN_TO_SORT_KEY).find(
            ([, sortKey]) => sortKey === state.sort.value.key,
        );

        return entry ? entry[0] : null;
    });

    const activeFiltersCount = computed<number>(() => {
        let n = 0;
        const f = state.filters.value;

        if (f.companyId !== null) {
            n += 1;
        }

        if (f.activityStatus !== null) {
            n += 1;
        }

        if (f.contractsScope !== null) {
            n += 1;
        }

        return n;
    });

    function onHeaderClick(columnKey: string): void {
        const sortKey = COLUMN_TO_SORT_KEY[columnKey];

        if (sortKey !== undefined) {
            state.setSort(sortKey);
        }
    }

    function onRowClick(row: DriverRow): void {
        router.visit(showRoute.url(row.id));
    }

    return {
        columns,
        state,
        activeSortColumnKey,
        activeFiltersCount,
        onHeaderClick,
        onRowClick,
    };
}
