import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import { useTableState } from '@/Composables/Shared/useTableState';
import type { TableState } from '@/Composables/Shared/useTableState';
import { show as contractsShowRoute } from '@/routes/user/contracts';
import type { DataTableColumn } from '@/types/ui';
import { formatDateFr } from '@/Utils/format/formatDateFr';
import {
    contractTypeBadgeTone,
    contractTypeShortLabel,
} from '@/Utils/labels/contractEnumLabels';

type ContractRow = App.Data.User.Contract.ContractListItemData;

export type ContractFilters = {
    vehicleId: number | null;
    companyId: number | null;
    type: 'lcd' | 'lld' | null;
    periodStart: string | null;
    periodEnd: string | null;
    hasDriver: 'yes' | 'no' | null;
};

export type ContractSortKey =
    | 'vehicle'
    | 'company'
    | 'startDate'
    | 'endDate'
    | 'duration'
    | 'type';

const DEFAULT_FILTERS: ContractFilters = {
    vehicleId: null,
    companyId: null,
    type: null,
    periodStart: null,
    periodEnd: null,
    hasDriver: null,
};

const SORT_KEYS: readonly ContractSortKey[] = [
    'vehicle',
    'company',
    'startDate',
    'endDate',
    'duration',
    'type',
];

export type ContractFilterChip = {
    key: keyof ContractFilters;
    label: string;
};

/**
 * Configuration colonnes + état tri/filtres pour la table de la page
 * Index Contracts (chantier D).
 *
 * Le rendu des cellules (badges, plates, dates) reste dans
 * `ContractsTable.vue` via les slots de `DataTable`.
 */
export function useContractsTable(opts: {
    contracts: Ref<readonly ContractRow[]>;
    vehicleOptions: Ref<readonly App.Data.User.Vehicle.VehicleOptionData[]>;
    companyOptions: Ref<readonly App.Data.User.Company.CompanyOptionData[]>;
}): {
    columns: ComputedRef<readonly DataTableColumn<ContractRow>[]>;
    rows: ComputedRef<ContractRow[]>;
    state: TableState<ContractRow, ContractFilters, ContractSortKey>;
    activeFilterChips: ComputedRef<ContractFilterChip[]>;
    removeFilter: (key: keyof ContractFilters) => void;
    shortLabel: typeof contractTypeShortLabel;
    badgeTone: typeof contractTypeBadgeTone;
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

    const state = useTableState<ContractRow, ContractFilters, ContractSortKey>({
        defaultFilters: DEFAULT_FILTERS,
        sortKeys: SORT_KEYS,
        parseFiltersFromUrl: (params) => ({
            vehicleId: parseIntOrNull(params.get('vehicleId')),
            companyId: parseIntOrNull(params.get('companyId')),
            type: parseTypeOrNull(params.get('type')),
            periodStart: params.get('periodStart'),
            periodEnd: params.get('periodEnd'),
            hasDriver: parseHasDriverOrNull(params.get('hasDriver')),
        }),
        serializeFiltersToUrl: (f) => ({
            vehicleId: f.vehicleId?.toString() ?? null,
            companyId: f.companyId?.toString() ?? null,
            type: f.type,
            periodStart: f.periodStart,
            periodEnd: f.periodEnd,
            hasDriver: f.hasDriver,
        }),
        applyFilter: (item, f) => {
            if (f.vehicleId !== null && item.vehicleId !== f.vehicleId) {
                return false;
            }

            if (f.companyId !== null && item.companyId !== f.companyId) {
                return false;
            }

            if (f.type !== null && item.contractType !== f.type) {
                return false;
            }

            // Période : le contrat doit chevaucher [periodStart, periodEnd].
            if (f.periodStart !== null && item.endDate < f.periodStart) {
                return false;
            }

            if (f.periodEnd !== null && item.startDate > f.periodEnd) {
                return false;
            }

            // hasDriver : `driverId` n'existe pas encore sur ContractListItemData
            // en V1 ; on le branchera dès que le DTO l'expose. Pour l'instant
            // ce filtre est neutre (toujours vrai).
            return true;
        },
        sortComparators: {
            vehicle: (a, b) =>
                a.vehicleLicensePlate.localeCompare(b.vehicleLicensePlate),
            company: (a, b) => a.companyShortCode.localeCompare(b.companyShortCode),
            startDate: (a, b) => a.startDate.localeCompare(b.startDate),
            endDate: (a, b) => a.endDate.localeCompare(b.endDate),
            duration: (a, b) => a.durationDays - b.durationDays,
            type: (a, b) => a.contractType.localeCompare(b.contractType),
        },
    });

    const rows = computed<ContractRow[]>(() => state.apply(opts.contracts.value));

    const activeFilterChips = computed<ContractFilterChip[]>(() => {
        const chips: ContractFilterChip[] = [];
        const f = state.filters.value;

        if (f.vehicleId !== null) {
            const v = opts.vehicleOptions.value.find((x) => x.id === f.vehicleId);
            chips.push({
                key: 'vehicleId',
                label: `Véhicule : ${v?.label ?? '#' + f.vehicleId}`,
            });
        }

        if (f.companyId !== null) {
            const c = opts.companyOptions.value.find((x) => x.id === f.companyId);
            chips.push({
                key: 'companyId',
                label: `Entreprise : ${c?.shortCode ?? '#' + f.companyId}`,
            });
        }

        if (f.type !== null) {
            chips.push({ key: 'type', label: `Type : ${f.type.toUpperCase()}` });
        }

        if (f.periodStart !== null || f.periodEnd !== null) {
            const start = f.periodStart === null ? '…' : formatDateFr(f.periodStart);
            const end = f.periodEnd === null ? '…' : formatDateFr(f.periodEnd);
            chips.push({
                key: 'periodStart',
                label: `Période : ${start} → ${end}`,
            });
        }

        if (f.hasDriver !== null) {
            chips.push({
                key: 'hasDriver',
                label: f.hasDriver === 'yes' ? 'Avec conducteur' : 'Sans conducteur',
            });
        }

        return chips;
    });

    function removeFilter(key: keyof ContractFilters): void {
        if (key === 'periodStart' || key === 'periodEnd') {
            // La pill "Période" couvre les 2 bornes — on les efface ensemble.
            state.setFilter('periodStart', null);
            state.setFilter('periodEnd', null);

            return;
        }

        state.setFilter(key, DEFAULT_FILTERS[key]);
    }

    const handleRowClick = (row: ContractRow): void => {
        router.visit(contractsShowRoute.url({ contract: row.id }));
    };

    return {
        columns,
        rows,
        state,
        activeFilterChips,
        removeFilter,
        shortLabel: contractTypeShortLabel,
        badgeTone: contractTypeBadgeTone,
        handleRowClick,
    };
}

function parseIntOrNull(value: string | null): number | null {
    if (value === null) {
        return null;
    }

    const n = Number.parseInt(value, 10);

    return Number.isNaN(n) ? null : n;
}

function parseTypeOrNull(value: string | null): 'lcd' | 'lld' | null {
    if (value === 'lcd' || value === 'lld') {
        return value;
    }

    return null;
}

function parseHasDriverOrNull(value: string | null): 'yes' | 'no' | null {
    if (value === 'yes' || value === 'no') {
        return value;
    }

    return null;
}
