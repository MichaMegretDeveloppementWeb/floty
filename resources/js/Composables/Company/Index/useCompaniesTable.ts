/**
 * Server-side Index table for Companies (ADR-0020).
 *
 * Specifics vs Drivers:
 *  - `isActive` tri-state filter (true / false / null)
 *  - `daysUsed` and `annualTaxDue` columns are rendered but NOT sortable
 *    (computed values from the fiscal aggregator)
 *
 * Rendering stays in `CompaniesTable.vue` (cell-* slots).
 */

import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import { useServerTableState } from '@/Composables/Shared/useServerTableState';
import type { ServerTableState } from '@/Composables/Shared/useServerTableState';
import { show as companyShowRoute } from '@/routes/user/companies';
import type { DataTableColumn } from '@/types/ui';

type CompanyRow = App.Data.User.Company.CompanyListItemData;

export type CompanySortKey = 'shortCode' | 'legalName' | 'siren' | 'city';

// Maps UI column key to backend sortKey (CompanyIndexQueryData whitelist).
// daysUsed and annualTaxDue have no entry because they are not sortable.
const COLUMN_TO_SORT_KEY: Partial<Record<string, CompanySortKey>> = {
    company: 'legalName',
    siren: 'siren',
    city: 'city',
};

export type CompanyFilters = {
    isActive: boolean | null;
    contractsScope: 'with' | 'without' | null;
    city: string | null;
    /** Year driving the financial columns. */
    year: number;
};

export function useCompaniesTable(opts: {
    query: App.Data.User.Company.CompanyIndexQueryData;
    selectedYear: number;
}): {
    columns: ComputedRef<readonly DataTableColumn<CompanyRow>[]>;
    state: ServerTableState<CompanyFilters>;
    activeSortColumnKey: ComputedRef<string | null>;
    onHeaderClick: (columnKey: string) => void;
    onRowClick: (row: CompanyRow) => void;
} {
    const state = useServerTableState<CompanyFilters>({
        only: ['companies', 'query', 'selectedYear'],
        initialPage: opts.query.page,
        initialPerPage: opts.query.perPage,
        initialSearch: opts.query.search ?? '',
        initialSortKey: opts.query.sortKey,
        initialSortDirection: opts.query.sortDirection,
        defaultFilters: {
            isActive: null,
            contractsScope: null,
            city: null,
            year: opts.selectedYear,
        },
        initialFilters: {
            isActive: opts.query.isActive,
            contractsScope: opts.query.contractsScope as
                | 'with'
                | 'without'
                | null,
            city: opts.query.city,
            year: opts.query.year ?? opts.selectedYear,
        },
        serializeFilters: (f) => ({
            // Boolean serialisation 1/0/null to match Spatie Data ?isActive=1 / ?isActive=0 / absent.
            isActive: f.isActive === null ? null : f.isActive ? 1 : 0,
            contractsScope: f.contractsScope,
            city: f.city,
            year: f.year,
        }),
    });

    // Year-dependent labels recompute automatically when `state.filters.value.year` changes.
    // Without this, columns would stay frozen on the initial year.
    const columns = computed<readonly DataTableColumn<CompanyRow>[]>(() => {
        const year = state.filters.value.year;

        return [
            { key: 'company', label: 'Entreprise' },
            { key: 'siren', label: 'SIREN', mono: true },
            { key: 'city', label: 'Ville' },
            { key: 'daysUsed', label: `Jours ${year}`, mono: true },
            { key: 'annualTaxDue', label: `Taxe ${year}` },
            { key: 'rentalPriceTotal', label: `Montant loyer ${year}` },
        ];
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
        // Columns without an entry in COLUMN_TO_SORT_KEY (daysUsed, annualTaxDue)
        // are intentional no-ops on click: no server-side sort on these computed values.
    }

    function onRowClick(row: CompanyRow): void {
        router.visit(companyShowRoute.url(row.id));
    }

    return {
        columns,
        state,
        activeSortColumnKey,
        onHeaderClick,
        onRowClick,
    };
}
