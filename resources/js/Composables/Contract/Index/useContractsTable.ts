/**
 * Configuration de la table Index Contracts (server-side, cf. ADR-0020).
 *
 * Particularités :
 *  - 5 filtres : vehicleId, companyId, driverId, type (lcd/lld),
 *    periodStart/periodEnd (chevauchement)
 *  - Search combo SQL : LIKE sur vehicle.license_plate/brand/model,
 *    company.short_code/legal_name, driver.first_name/last_name
 *  - Toutes les 6 colonnes triables (vehicle, company, startDate,
 *    endDate, duration via DATEDIFF, type)
 *
 * Le rendu (badges, plates, dates) reste dans `ContractsTable.vue`.
 */

import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import { useServerTableState } from '@/Composables/Shared/useServerTableState';
import type { ServerTableState } from '@/Composables/Shared/useServerTableState';
import { show as contractsShowRoute } from '@/routes/user/contracts';
import type { DataTableColumn } from '@/types/ui';
import { formatDateFr } from '@/Utils/format/formatDateFr';
import {
    contractTypeBadgeTone,
    contractTypeShortLabel,
} from '@/Utils/labels/contractEnumLabels';

type ContractRow = App.Data.User.Contract.ContractListItemData;

export type ContractSortKey =
    | 'vehicle'
    | 'company'
    | 'startDate'
    | 'endDate'
    | 'duration'
    | 'type';

// Mapping clé colonne UI → sortKey backend (whitelist
// ContractIndexQueryData::allowedSortKeys).
const COLUMN_TO_SORT_KEY: Partial<Record<string, ContractSortKey>> = {
    vehicleLicensePlate: 'vehicle',
    companyShortCode: 'company',
    startDate: 'startDate',
    endDate: 'endDate',
    durationDays: 'duration',
    contractType: 'type',
};

export type ContractFilters = {
    vehicleId: number | null;
    companyId: number | null;
    driverId: number | null;
    type: 'lcd' | 'lld' | null;
    periodStart: string | null;
    periodEnd: string | null;
};

export type ContractFilterChip = {
    key: keyof ContractFilters;
    label: string;
};

export function useContractsTable(opts: {
    query: App.Data.User.Contract.ContractIndexQueryData;
    vehicleOptions: readonly App.Data.User.Vehicle.VehicleOptionData[];
    companyOptions: readonly App.Data.User.Company.CompanyOptionData[];
    driverOptions: readonly App.Data.User.Driver.DriverOptionData[];
}): {
    columns: readonly DataTableColumn<ContractRow>[];
    state: ServerTableState<ContractFilters>;
    activeSortColumnKey: ComputedRef<string | null>;
    activeFilterChips: ComputedRef<ContractFilterChip[]>;
    activeFiltersCount: ComputedRef<number>;
    onHeaderClick: (columnKey: string) => void;
    onRowClick: (row: ContractRow) => void;
    shortLabel: typeof contractTypeShortLabel;
    badgeTone: typeof contractTypeBadgeTone;
} {
    const columns: readonly DataTableColumn<ContractRow>[] = [
        { key: 'vehicleLicensePlate', label: 'Véhicule' },
        { key: 'companyShortCode', label: 'Entreprise' },
        { key: 'startDate', label: 'Du', mono: true },
        { key: 'endDate', label: 'Au', mono: true },
        { key: 'durationDays', label: 'Durée', align: 'right', mono: true },
        { key: 'contractType', label: 'Type' },
    ];

    const state = useServerTableState<ContractFilters>({
        only: ['contracts', 'query'],
        initialPage: opts.query.page,
        initialPerPage: opts.query.perPage,
        initialSearch: opts.query.search ?? '',
        initialSortKey: opts.query.sortKey,
        initialSortDirection: opts.query.sortDirection,
        defaultFilters: {
            vehicleId: null,
            companyId: null,
            driverId: null,
            type: null,
            periodStart: null,
            periodEnd: null,
        },
        initialFilters: {
            vehicleId: opts.query.vehicleId,
            companyId: opts.query.companyId,
            driverId: opts.query.driverId,
            type: opts.query.type as 'lcd' | 'lld' | null,
            periodStart: opts.query.periodStart,
            periodEnd: opts.query.periodEnd,
        },
        serializeFilters: (f) => ({
            vehicleId: f.vehicleId,
            companyId: f.companyId,
            driverId: f.driverId,
            type: f.type,
            periodStart: f.periodStart,
            periodEnd: f.periodEnd,
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

    const activeFilterChips = computed<ContractFilterChip[]>(() => {
        const chips: ContractFilterChip[] = [];
        const f = state.filters.value;

        if (f.vehicleId !== null) {
            const v = opts.vehicleOptions.find((x) => x.id === f.vehicleId);
            chips.push({
                key: 'vehicleId',
                label: `Véhicule : ${v?.label ?? '#' + f.vehicleId}`,
            });
        }

        if (f.companyId !== null) {
            const c = opts.companyOptions.find((x) => x.id === f.companyId);
            chips.push({
                key: 'companyId',
                label: `Entreprise : ${c?.shortCode ?? '#' + f.companyId}`,
            });
        }

        if (f.driverId !== null) {
            const d = opts.driverOptions.find((x) => x.id === f.driverId);
            chips.push({
                key: 'driverId',
                label: `Conducteur : ${d?.fullName ?? '#' + f.driverId}`,
            });
        }

        if (f.type !== null) {
            chips.push({
                key: 'type',
                label: `Type : ${f.type.toUpperCase()}`,
            });
        }

        if (f.periodStart !== null || f.periodEnd !== null) {
            const start
                = f.periodStart === null ? '…' : formatDateFr(f.periodStart);
            const end = f.periodEnd === null ? '…' : formatDateFr(f.periodEnd);
            chips.push({
                key: 'periodStart',
                label: `Période : ${start} → ${end}`,
            });
        }

        return chips;
    });

    const activeFiltersCount = computed<number>(
        () => activeFilterChips.value.length,
    );

    function onHeaderClick(columnKey: string): void {
        const sortKey = COLUMN_TO_SORT_KEY[columnKey];

        if (sortKey !== undefined) {
            state.setSort(sortKey);
        }
    }

    function onRowClick(row: ContractRow): void {
        router.visit(contractsShowRoute.url({ contract: row.id }));
    }

    return {
        columns,
        state,
        activeSortColumnKey,
        activeFilterChips,
        activeFiltersCount,
        onHeaderClick,
        onRowClick,
        shortLabel: contractTypeShortLabel,
        badgeTone: contractTypeBadgeTone,
    };
}
