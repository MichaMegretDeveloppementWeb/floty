import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import { useServerTableState } from '@/Composables/Shared/useServerTableState';
import type { ServerTableState } from '@/Composables/Shared/useServerTableState';
import { show as vehicleEventShowRoute } from '@/routes/user/vehicles/events';
import type { DataTableColumn } from '@/types/ui/data-table';

type VehicleEventRow = App.Data.User.VehicleEvent.VehicleEventListItemData;
type Query = App.Data.User.VehicleEvent.VehicleEventIndexQueryData;

export type VehicleEventFilters = {
    /** Natures (UI « Nature »), key kept as `categories` to match the backend. */
    categories: string[];
    year: number | null;
    /** Filtres dédiés (hors recherche libre : conflit avec les plaques). */
    garage: string;
    postal_code: string;
};

export type FilterChip = { key: string; label: string };

/** Maps a sortable column key to its backend sortKey (whitelist). */
const COLUMN_TO_SORT_KEY: Record<string, string> = {
    vehicleLicensePlate: 'vehicle',
    startDate: 'startDate',
    title: 'title',
    amount: 'amount',
};

const SORT_KEY_TO_COLUMN: Record<string, string> = Object.fromEntries(
    Object.entries(COLUMN_TO_SORT_KEY).map(([col, key]) => [key, col]),
);

export function useVehicleEventsTable(opts: { query: Query }): {
    columns: readonly DataTableColumn<VehicleEventRow>[];
    state: ServerTableState<VehicleEventFilters>;
    /** Saisies débouncées des filtres texte garage / code postal (300 ms). */
    garageInput: Ref<string>;
    postalCodeInput: Ref<string>;
    activeSortColumnKey: ComputedRef<string | null>;
    activeFiltersCount: ComputedRef<number>;
    activeFilterChips: ComputedRef<FilterChip[]>;
    removeFilterChip: (key: string) => void;
    onHeaderClick: (columnKey: string) => void;
    onRowClick: (row: VehicleEventRow) => void;
} {
    const columns: readonly DataTableColumn<VehicleEventRow>[] = [
        { key: 'vehicleLicensePlate', label: 'Véhicule', mono: true },
        { key: 'startDate', label: 'Date', mono: true },
        { key: 'duration', label: 'Durée' },
        { key: 'title', label: 'Intitulé' },
        { key: 'categories', label: 'Nature' },
        { key: 'garage', label: 'Garage' },
        { key: 'amount', label: 'Montant', align: 'right', mono: true },
    ];

    const state = useServerTableState<VehicleEventFilters>({
        only: ['events', 'totalAmountCents', 'query'],
        initialPage: opts.query.page,
        initialPerPage: opts.query.perPage,
        initialSearch: opts.query.search ?? '',
        initialSortKey: opts.query.sortKey,
        initialSortDirection: opts.query.sortDirection,
        defaultFilters: { categories: [], year: null, garage: '', postal_code: '' },
        initialFilters: {
            categories: opts.query.categories ?? [],
            year: opts.query.year,
            garage: opts.query.garage ?? '',
            postal_code: opts.query.postalCode ?? '',
        },
        serializeFilters: (f) => ({
            categories: f.categories,
            year: f.year,
            garage: f.garage.trim() !== '' ? f.garage.trim() : null,
            postalCode: f.postal_code.trim() !== '' ? f.postal_code.trim() : null,
        }),
    });

    // Text filters debounce locally, then land in the state in one batch;
    // chip removal / clearFilters mirror back into the inputs.
    const garageInput = ref<string>(opts.query.garage ?? '');
    const postalCodeInput = ref<string>(opts.query.postalCode ?? '');
    let textFilterTimer: ReturnType<typeof setTimeout> | null = null;

    watch([garageInput, postalCodeInput], ([garage, postalCode]) => {
        if (garage === state.filters.value.garage && postalCode === state.filters.value.postal_code) {
            return;
        }

        if (textFilterTimer !== null) {
            clearTimeout(textFilterTimer);
        }

        textFilterTimer = setTimeout(() => {
            textFilterTimer = null;
            state.patchFilters({ garage, postal_code: postalCode });
        }, 300);
    });

    watch(
        () => [state.filters.value.garage, state.filters.value.postal_code] as const,
        ([garage, postalCode]) => {
            garageInput.value = garage;
            postalCodeInput.value = postalCode;
        },
    );

    onBeforeUnmount(() => {
        if (textFilterTimer !== null) {
            clearTimeout(textFilterTimer);
        }
    });

    const activeSortColumnKey = computed<string | null>(() => {
        const key = state.sort.value.key;

        return key !== null ? (SORT_KEY_TO_COLUMN[key] ?? null) : null;
    });

    const activeFiltersCount = computed<number>(() => {
        const f = state.filters.value;

        return f.categories.length
            + (f.year !== null ? 1 : 0)
            + (f.garage.trim() !== '' ? 1 : 0)
            + (f.postal_code.trim() !== '' ? 1 : 0);
    });

    const activeFilterChips = computed<FilterChip[]>(() => {
        const f = state.filters.value;
        const chips: FilterChip[] = f.categories.map((category) => ({
            key: `category:${category}`,
            label: `Nature : ${category}`,
        }));

        if (f.year !== null) {
            chips.push({ key: 'year', label: `Année : ${f.year}` });
        }

        if (f.garage.trim() !== '') {
            chips.push({ key: 'garage', label: `Garage : ${f.garage.trim()}` });
        }

        if (f.postal_code.trim() !== '') {
            chips.push({ key: 'postal_code', label: `Code postal : ${f.postal_code.trim()}` });
        }

        return chips;
    });

    function removeFilterChip(key: string): void {
        const f = state.filters.value;

        if (key === 'year') {
            state.setFilter('year', null);

            return;
        }

        if (key === 'garage' || key === 'postal_code') {
            state.setFilter(key, '');

            return;
        }

        const separator = key.indexOf(':');
        const kind = key.slice(0, separator);
        const value = key.slice(separator + 1);

        if (kind === 'category') {
            state.setFilter('categories', f.categories.filter((c) => c !== value));
        }
    }

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
        garageInput,
        postalCodeInput,
        activeSortColumnKey,
        activeFiltersCount,
        activeFilterChips,
        removeFilterChip,
        onHeaderClick,
        onRowClick,
    };
}
