import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import { useServerTableState } from '@/Composables/Shared/useServerTableState';
import type { ServerTableState } from '@/Composables/Shared/useServerTableState';
import { show as vehicleEventShowRoute } from '@/routes/user/vehicles/events';
import type { DataTableColumn } from '@/types/ui/data-table';

type VehicleEventRow = App.Data.User.VehicleEvent.VehicleEventListItemData;
type Query = App.Data.User.VehicleEvent.VehicleEventIndexQueryData;

export type VehicleEventFilters = {
    types: string[];
    categories: string[];
    year: number | null;
};

/** Maps a sortable column key to its backend sortKey (whitelist). */
const COLUMN_TO_SORT_KEY: Record<string, string> = {
    vehicleLicensePlate: 'vehicle',
    startDate: 'startDate',
    title: 'type',
    amount: 'amount',
};

const SORT_KEY_TO_COLUMN: Record<string, string> = Object.fromEntries(
    Object.entries(COLUMN_TO_SORT_KEY).map(([col, key]) => [key, col]),
);

export function useVehicleEventsTable(opts: { query: Query }): {
    columns: readonly DataTableColumn<VehicleEventRow>[];
    state: ServerTableState<VehicleEventFilters>;
    activeSortColumnKey: ComputedRef<string | null>;
    activeFiltersCount: ComputedRef<number>;
    onHeaderClick: (columnKey: string) => void;
    onRowClick: (row: VehicleEventRow) => void;
} {
    const columns: readonly DataTableColumn<VehicleEventRow>[] = [
        { key: 'vehicleLicensePlate', label: 'Véhicule', mono: true },
        { key: 'startDate', label: 'Date', mono: true },
        { key: 'title', label: 'Intitulé' },
        { key: 'categories', label: 'Catégories' },
        { key: 'amount', label: 'Montant', align: 'right', mono: true },
    ];

    const state = useServerTableState<VehicleEventFilters>({
        only: ['events', 'totalAmountCents', 'query'],
        initialPage: opts.query.page,
        initialPerPage: opts.query.perPage,
        initialSearch: opts.query.search ?? '',
        initialSortKey: opts.query.sortKey,
        initialSortDirection: opts.query.sortDirection,
        defaultFilters: { types: [], categories: [], year: null },
        initialFilters: {
            types: opts.query.types ?? [],
            categories: opts.query.categories ?? [],
            year: opts.query.year,
        },
        serializeFilters: (f) => ({
            types: f.types,
            categories: f.categories,
            year: f.year,
        }),
    });

    const activeSortColumnKey = computed<string | null>(() => {
        const key = state.sort.value.key;

        return key !== null ? (SORT_KEY_TO_COLUMN[key] ?? null) : null;
    });

    const activeFiltersCount = computed<number>(() => {
        const f = state.filters.value;

        return f.types.length + f.categories.length + (f.year !== null ? 1 : 0);
    });

    function onHeaderClick(columnKey: string): void {
        const sortKey = COLUMN_TO_SORT_KEY[columnKey];

        if (sortKey !== undefined) {
            state.setSort(sortKey);
        }
    }

    function onRowClick(row: VehicleEventRow): void {
        router.visit(
            vehicleEventShowRoute.url({ vehicle: row.vehicleId, vehicleEvent: row.id }),
        );
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
