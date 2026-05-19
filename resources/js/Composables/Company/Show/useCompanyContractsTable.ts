/**
 * Server-side Contracts table for the Company Show page (ADR-0020).
 *
 * Pagination, sort and period filter handled by `useServerTableState`,
 * Inertia v3 partial reload on `contracts` + `contractsQuery` only.
 *
 * Filters scope: period only. No search, no type/vehicle/driver filter:
 * the Company page is already scoped to one entreprise, kept dense and focal.
 *
 * The period filter lives locally in this tab, with no dependency on any global year selector.
 */

import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import { useServerTableState } from '@/Composables/Shared/useServerTableState';
import type { ServerTableState } from '@/Composables/Shared/useServerTableState';
import { show as contractsShowRoute } from '@/routes/user/contracts';
import type { DataTableColumn } from '@/types/ui';
import {
    contractTypeBadgeTone,
    contractTypeShortLabel,
} from '@/Utils/labels/contractEnumLabels';

type ContractRow = App.Data.User.Contract.ContractListItemData;

export type CompanyContractSortKey =
    | 'vehicle'
    | 'startDate'
    | 'endDate'
    | 'duration'
    | 'type';

const COLUMN_TO_SORT_KEY: Partial<Record<string, CompanyContractSortKey>> = {
    vehicleLicensePlate: 'vehicle',
    startDate: 'startDate',
    endDate: 'endDate',
    durationDays: 'duration',
    contractType: 'type',
};

export type CompanyContractFilters = {
    /**
     * Full-year pill selector, shared with Fiscalité/Facturation tabs to preserve
     * the exercise when switching tabs. Mutually exclusive with `periodStart`/`periodEnd`
     * on the UI (YearPills vs custom picker toggle).
     */
    year: number | null;
    periodStart: string | null;
    periodEnd: string | null;
};

export function useCompanyContractsTable(opts: {
    query: App.Data.User.Contract.ContractIndexQueryData;
}): {
    columns: readonly DataTableColumn<ContractRow>[];
    state: ServerTableState<CompanyContractFilters>;
    activeSortColumnKey: ComputedRef<string | null>;
    onHeaderClick: (columnKey: string) => void;
    onRowClick: (row: ContractRow) => void;
    shortLabel: typeof contractTypeShortLabel;
    badgeTone: typeof contractTypeBadgeTone;
} {
    const columns: readonly DataTableColumn<ContractRow>[] = [
        { key: 'vehicleLicensePlate', label: 'Véhicule' },
        { key: 'startDate', label: 'Du', mono: true },
        { key: 'endDate', label: 'Au', mono: true },
        { key: 'durationDays', label: 'Durée', align: 'right', mono: true },
        { key: 'contractType', label: 'Type' },
        { key: 'totalTax', label: 'Taxe', align: 'right', mono: true },
        { key: 'rentalPrice', label: 'Montant loyer', align: 'right', mono: true },
    ];

    const state = useServerTableState<CompanyContractFilters>({
        only: ['contracts', 'contractsQuery', 'contractsStats'],
        initialPage: opts.query.page,
        initialPerPage: opts.query.perPage,
        initialSearch: '',
        initialSortKey: opts.query.sortKey,
        initialSortDirection: opts.query.sortDirection,
        defaultFilters: {
            year: null,
            periodStart: null,
            periodEnd: null,
        },
        initialFilters: {
            year: opts.query.year,
            periodStart: opts.query.periodStart,
            periodEnd: opts.query.periodEnd,
        },
        serializeFilters: (f) => ({
            year: f.year === null ? null : String(f.year),
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
        onHeaderClick,
        onRowClick,
        shortLabel: contractTypeShortLabel,
        badgeTone: contractTypeBadgeTone,
    };
}
