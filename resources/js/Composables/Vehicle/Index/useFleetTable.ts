import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import { useTableState } from '@/Composables/Shared/useTableState';
import type { TableState } from '@/Composables/Shared/useTableState';
import { show as vehiclesShowRoute } from '@/routes/user/vehicles';
import type { DataTableColumn } from '@/types/ui';

type VehicleRow = App.Data.User.Vehicle.VehicleListItemData;

export type FleetFilters = {
    search: string;
    status: App.Enums.Vehicle.VehicleStatus | null;
    fullYearTaxMin: number | null;
    fullYearTaxMax: number | null;
};

export type FleetSortKey = 'plate' | 'model' | 'firstReg' | 'fullYearTax';

const DEFAULT_FILTERS: FleetFilters = {
    search: '',
    status: null,
    fullYearTaxMin: null,
    fullYearTaxMax: null,
};

const SORT_KEYS: readonly FleetSortKey[] = [
    'plate',
    'model',
    'firstReg',
    'fullYearTax',
];

export type FleetFilterChip = {
    key: keyof FleetFilters;
    label: string;
};

const STATUS_LABELS: Record<App.Enums.Vehicle.VehicleStatus, string> = {
    active: 'Active',
    maintenance: 'Maintenance',
    sold: 'Vendu',
    destroyed: 'Détruit',
    other: 'Autre',
};

export function useFleetTable(opts: {
    vehicles: Ref<readonly VehicleRow[]>;
    fiscalYear: Ref<number>;
}): {
    columns: ComputedRef<readonly DataTableColumn<VehicleRow>[]>;
    rows: ComputedRef<VehicleRow[]>;
    state: TableState<VehicleRow, FleetFilters, FleetSortKey>;
    activeFilterChips: ComputedRef<FleetFilterChip[]>;
    removeFilter: (key: keyof FleetFilters) => void;
    statusLabel: Record<string, string>;
    statusDotClass: Record<string, string>;
    handleRowClick: (row: VehicleRow) => void;
} {
    const columns = computed<readonly DataTableColumn<VehicleRow>[]>(() => [
        { key: 'licensePlate', label: 'Immatriculation' },
        { key: 'model', label: 'Modèle' },
        { key: 'firstFrenchRegistrationDate', label: '1ʳᵉ immat.', mono: true },
        {
            key: 'fullYearTax',
            label: `Coût plein ${opts.fiscalYear.value}`,
            align: 'right',
        },
    ]);

    const state = useTableState<VehicleRow, FleetFilters, FleetSortKey>({
        defaultFilters: DEFAULT_FILTERS,
        sortKeys: SORT_KEYS,
        parseFiltersFromUrl: (params) => ({
            search: params.get('search') ?? '',
            status: parseStatus(params.get('status')),
            fullYearTaxMin: parseFloatOrNull(params.get('fullYearTaxMin')),
            fullYearTaxMax: parseFloatOrNull(params.get('fullYearTaxMax')),
        }),
        serializeFiltersToUrl: (f) => ({
            search: f.search === '' ? null : f.search,
            status: f.status,
            fullYearTaxMin: f.fullYearTaxMin?.toString() ?? null,
            fullYearTaxMax: f.fullYearTaxMax?.toString() ?? null,
        }),
        applyFilter: (item, f) => {
            if (f.search !== '') {
                const needle = f.search.toLowerCase();
                const haystack =
                    `${item.licensePlate} ${item.brand} ${item.model}`.toLowerCase();

                if (!haystack.includes(needle)) {
                    return false;
                }
            }

            if (f.status !== null && item.currentStatus !== f.status) {
                return false;
            }

            if (
                f.fullYearTaxMin !== null
                && item.fullYearTax < f.fullYearTaxMin
            ) {
                return false;
            }

            if (
                f.fullYearTaxMax !== null
                && item.fullYearTax > f.fullYearTaxMax
            ) {
                return false;
            }

            return true;
        },
        sortComparators: {
            plate: (a, b) => a.licensePlate.localeCompare(b.licensePlate),
            model: (a, b) =>
                `${a.brand} ${a.model}`.localeCompare(`${b.brand} ${b.model}`),
            firstReg: (a, b) =>
                a.firstFrenchRegistrationDate.localeCompare(
                    b.firstFrenchRegistrationDate,
                ),
            fullYearTax: (a, b) => a.fullYearTax - b.fullYearTax,
        },
    });

    const rows = computed<VehicleRow[]>(() => state.apply(opts.vehicles.value));

    const activeFilterChips = computed<FleetFilterChip[]>(() => {
        const chips: FleetFilterChip[] = [];
        const f = state.filters.value;

        if (f.search !== '') {
            chips.push({ key: 'search', label: `Recherche : « ${f.search} »` });
        }

        if (f.status !== null) {
            chips.push({
                key: 'status',
                label: `Statut : ${STATUS_LABELS[f.status] ?? f.status}`,
            });
        }

        if (f.fullYearTaxMin !== null || f.fullYearTaxMax !== null) {
            const min = f.fullYearTaxMin === null ? '…' : `${f.fullYearTaxMin} €`;
            const max = f.fullYearTaxMax === null ? '…' : `${f.fullYearTaxMax} €`;
            chips.push({
                key: 'fullYearTaxMin',
                label: `Coût plein : ${min} → ${max}`,
            });
        }

        return chips;
    });

    function removeFilter(key: keyof FleetFilters): void {
        if (key === 'fullYearTaxMin' || key === 'fullYearTaxMax') {
            state.setFilter('fullYearTaxMin', null);
            state.setFilter('fullYearTaxMax', null);

            return;
        }

        state.setFilter(key, DEFAULT_FILTERS[key]);
    }

    const statusLabel: Record<string, string> = STATUS_LABELS;

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

    return {
        columns,
        rows,
        state,
        activeFilterChips,
        removeFilter,
        statusLabel,
        statusDotClass,
        handleRowClick,
    };
}

function parseStatus(value: string | null): App.Enums.Vehicle.VehicleStatus | null {
    if (
        value === 'active'
        || value === 'maintenance'
        || value === 'sold'
        || value === 'destroyed'
        || value === 'other'
    ) {
        return value;
    }

    return null;
}

function parseFloatOrNull(value: string | null): number | null {
    if (value === null) {
        return null;
    }

    const n = Number.parseFloat(value);

    return Number.isNaN(n) ? null : n;
}
