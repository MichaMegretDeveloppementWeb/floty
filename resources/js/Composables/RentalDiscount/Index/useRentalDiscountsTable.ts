/**
 * Server-side Index table for Rental Discounts (ADR-0020).
 *
 * Filters (see RentalDiscountIndexQueryData):
 *  - companyId: discounts for a given company
 *  - status: 'active' | 'planned' | 'expired' (SQL comparison vs. today)
 */

import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import { useServerTableState } from '@/Composables/Shared/useServerTableState';
import type { ServerTableState } from '@/Composables/Shared/useServerTableState';
import { show as showRoute } from '@/routes/user/rental-discounts';
import type { DataTableColumn } from '@/types/ui';

type RentalDiscountRow = App.Data.User.RentalDiscount.RentalDiscountListItemData;

export type RentalDiscountSortKey = 'company' | 'period' | 'discount' | 'createdAt';

const COLUMN_TO_SORT_KEY: Partial<Record<string, RentalDiscountSortKey>> = {
    company: 'company',
    period: 'period',
    discount: 'discount',
};

export type RentalDiscountFilters = {
    companyId: number | null;
    status: 'active' | 'planned' | 'expired' | null;
};

export function useRentalDiscountsTable(
    query: App.Data.User.RentalDiscount.RentalDiscountIndexQueryData,
): {
    columns: readonly DataTableColumn<RentalDiscountRow>[];
    state: ServerTableState<RentalDiscountFilters>;
    activeSortColumnKey: ComputedRef<string | null>;
    activeFiltersCount: ComputedRef<number>;
    onHeaderClick: (columnKey: string) => void;
    onRowClick: (row: RentalDiscountRow) => void;
} {
    const columns: readonly DataTableColumn<RentalDiscountRow>[] = [
        { key: 'company', label: 'Entreprise' },
        { key: 'period', label: 'Période' },
        { key: 'discount', label: 'Réduction', mono: true },
        { key: 'vehicles', label: 'Véhicules' },
        { key: 'status', label: 'Statut' },
    ];

    const state = useServerTableState<RentalDiscountFilters>({
        only: ['rentalDiscounts', 'stats', 'query'],
        initialPage: query.page,
        initialPerPage: query.perPage,
        initialSearch: query.search ?? '',
        initialSortKey: query.sortKey,
        initialSortDirection: query.sortDirection,
        defaultFilters: {
            companyId: null,
            status: null,
        },
        initialFilters: {
            companyId: query.companyId,
            status: query.status as RentalDiscountFilters['status'],
        },
        serializeFilters: (f) => ({
            companyId: f.companyId,
            status: f.status,
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

    const activeFiltersCount = computed<number>(() => {
        let n = 0;
        const f = state.filters.value;

        if (f.companyId !== null) {
            n += 1;
        }

        if (f.status !== null) {
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

    function onRowClick(row: RentalDiscountRow): void {
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
