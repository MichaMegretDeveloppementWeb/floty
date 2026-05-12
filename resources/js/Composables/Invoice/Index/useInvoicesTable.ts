/**
 * Configuration de la table Index Invoices (server-side, cf. ADR-0020).
 *
 * Filtres : `companyId`, `year`, `month`. Search SQL : LIKE sur
 * `invoice_number`, `company.short_code`, `company.legal_name`.
 *
 * Whitelist sortKey backend : `invoiceNumber`, `company`, `period`,
 * `totalHt`, `generatedAt`. Toutes traduisibles en SQL pure.
 */

import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef, WritableComputedRef } from 'vue';
import { useServerTableState } from '@/Composables/Shared/useServerTableState';
import type { ServerTableState } from '@/Composables/Shared/useServerTableState';
import { show as invoicesShowRoute } from '@/routes/user/invoices';
import type { DataTableColumn } from '@/types/ui';
import { MONTH_LABELS } from '@/Utils/format/monthLabels';

type InvoiceRow = App.Data.User.Invoice.InvoiceListItemData;

export type InvoiceSortKey =
    | 'invoiceNumber'
    | 'company'
    | 'period'
    | 'totalHt'
    | 'generatedAt';

const COLUMN_TO_SORT_KEY: Partial<Record<string, InvoiceSortKey>> = {
    invoiceNumber: 'invoiceNumber',
    companyShortCode: 'company',
    period: 'period',
    totalHtCents: 'totalHt',
    generatedAt: 'generatedAt',
};

export type InvoiceFilters = {
    companyId: number | null;
    year: number | null;
    month: number | null;
    divergentOnly: boolean;
};

export type InvoiceFilterChip = {
    key: keyof InvoiceFilters;
    label: string;
};

export function useInvoicesTable(opts: {
    query: App.Data.User.Invoice.InvoiceIndexQueryData;
    companyOptions: readonly App.Data.User.Company.CompanyOptionData[];
}): {
    columns: readonly DataTableColumn<InvoiceRow>[];
    state: ServerTableState<InvoiceFilters>;
    activeSortColumnKey: ComputedRef<string | null>;
    activeFilterChips: ComputedRef<InvoiceFilterChip[]>;
    activeFiltersCount: ComputedRef<number>;
    // V-models filtres (T11 / E.6 · push depuis Index.vue).
    searchModel: WritableComputedRef<string>;
    companyIdModel: WritableComputedRef<number | null>;
    yearModel: WritableComputedRef<number | null>;
    monthModel: WritableComputedRef<number | null>;
    divergentOnlyModel: WritableComputedRef<boolean>;
    onHeaderClick: (columnKey: string) => void;
    onRowClick: (row: InvoiceRow) => void;
} {
    const columns: readonly DataTableColumn<InvoiceRow>[] = [
        { key: 'invoiceNumber', label: 'N°', mono: true },
        { key: 'companyShortCode', label: 'Entreprise' },
        { key: 'period', label: 'Période', mono: true },
        { key: 'totalHtCents', label: 'Total HT', align: 'right', mono: true },
        { key: 'generatedAt', label: 'Émise le', mono: true },
    ];

    const state = useServerTableState<InvoiceFilters>({
        only: ['invoices', 'query'],
        initialPage: opts.query.page,
        initialPerPage: opts.query.perPage,
        initialSearch: opts.query.search ?? '',
        initialSortKey: opts.query.sortKey,
        initialSortDirection: opts.query.sortDirection,
        defaultFilters: {
            companyId: null,
            year: null,
            month: null,
            divergentOnly: false,
        },
        initialFilters: {
            companyId: opts.query.companyId,
            year: opts.query.year,
            month: opts.query.month,
            divergentOnly: opts.query.divergentOnly,
        },
        serializeFilters: (f) => ({
            companyId: f.companyId,
            year: f.year,
            month: f.month,
            // `divergentOnly = false` est l'état par défaut · on le retire
            // de l'URL pour rester propre. Seul `true` est sérialisé,
            // sous forme de chaîne `'1'` (Laravel rule `boolean` accepte
            // `'1'`, `1`, `true` indifféremment).
            divergentOnly: f.divergentOnly ? '1' : null,
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

    const activeFilterChips = computed<InvoiceFilterChip[]>(() => {
        const chips: InvoiceFilterChip[] = [];
        const f = state.filters.value;

        if (f.companyId !== null) {
            const c = opts.companyOptions.find((x) => x.id === f.companyId);
            chips.push({
                key: 'companyId',
                label: `Entreprise : ${c?.shortCode ?? '#' + f.companyId}`,
            });
        }

        if (f.year !== null) {
            chips.push({ key: 'year', label: `Année : ${f.year}` });
        }

        if (f.month !== null) {
            chips.push({ key: 'month', label: `Mois : ${MONTH_LABELS[f.month - 1] ?? f.month}` });
        }

        if (f.divergentOnly) {
            chips.push({ key: 'divergentOnly', label: 'À régénérer uniquement' });
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

    function onRowClick(row: InvoiceRow): void {
        router.visit(invoicesShowRoute.url({ invoice: row.id }));
    }

    // V-models filtres : exposés depuis le composable pour libérer la
    // page Index.vue de toute logique de plumbing (T11 / E.6).
    const searchModel = computed<string>({
        get: () => state.search.value,
        set: (value: string) => {
            state.search.value = value;
        },
    });

    const companyIdModel = computed<number | null>({
        get: () => state.filters.value.companyId,
        set: (value: number | null) => {
            state.setFilter('companyId', value);
        },
    });

    const yearModel = computed<number | null>({
        get: () => state.filters.value.year,
        set: (value: number | null) => {
            state.setFilter('year', value);
        },
    });

    const monthModel = computed<number | null>({
        get: () => state.filters.value.month,
        set: (value: number | null) => {
            state.setFilter('month', value);
        },
    });

    const divergentOnlyModel = computed<boolean>({
        get: () => state.filters.value.divergentOnly,
        set: (value: boolean) => {
            state.setFilter('divergentOnly', value);
        },
    });

    return {
        columns,
        state,
        activeSortColumnKey,
        activeFilterChips,
        activeFiltersCount,
        searchModel,
        companyIdModel,
        yearModel,
        monthModel,
        divergentOnlyModel,
        onHeaderClick,
        onRowClick,
    };
}
