/**
 * Configuration de la table Index Déclarations (server-side, cf.
 * ADR-0020). Phase 11 D4.
 *
 * Filtres : `companyId`, `fiscalYear`, `status`, `obsoleteOnly`. Pas de
 * search texte (peu utile sur les déclarations).
 *
 * Whitelist sortKey backend : `company`, `fiscalYear`, `status`,
 * `generatedAt`.
 */

import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef, WritableComputedRef } from 'vue';
import { useServerTableState } from '@/Composables/Shared/useServerTableState';
import type { ServerTableState } from '@/Composables/Shared/useServerTableState';
import { show as declarationsShowRoute } from '@/routes/user/declarations';
import type { DataTableColumn } from '@/types/ui';

type DeclarationRow = App.Data.User.FiscalDeclaration.DeclarationListItemData;

export type DeclarationSortKey =
    | 'company'
    | 'fiscalYear'
    | 'status'
    | 'generatedAt';

const COLUMN_TO_SORT_KEY: Partial<Record<string, DeclarationSortKey>> = {
    companyShortCode: 'company',
    fiscalYear: 'fiscalYear',
    status: 'status',
    generatedAt: 'generatedAt',
};

export type DeclarationFilters = {
    companyId: number | null;
    fiscalYear: number | null;
    status: App.Enums.FiscalDeclaration.FiscalDeclarationStatus | null;
    obsoleteOnly: boolean;
};

export type DeclarationFilterChip = {
    key: keyof DeclarationFilters;
    label: string;
};

export type DeclarationCompanyOption = {
    id: number;
    shortCode: string;
    legalName: string;
};

export function useDeclarationsIndex(opts: {
    query: App.Data.User.FiscalDeclaration.DeclarationIndexQueryData;
    companyOptions: readonly DeclarationCompanyOption[];
}): {
    columns: readonly DataTableColumn<DeclarationRow>[];
    state: ServerTableState<DeclarationFilters>;
    activeSortColumnKey: ComputedRef<string | null>;
    activeFilterChips: ComputedRef<DeclarationFilterChip[]>;
    activeFiltersCount: ComputedRef<number>;
    companyIdModel: WritableComputedRef<number | null>;
    fiscalYearModel: WritableComputedRef<number | null>;
    statusModel: WritableComputedRef<App.Enums.FiscalDeclaration.FiscalDeclarationStatus | null>;
    obsoleteOnlyModel: WritableComputedRef<boolean>;
    onHeaderClick: (columnKey: string) => void;
    onRowClick: (row: DeclarationRow) => void;
} {
    const columns: readonly DataTableColumn<DeclarationRow>[] = [
        { key: 'companyShortCode', label: 'Entreprise' },
        { key: 'fiscalYear', label: 'Année', mono: true, align: 'center' },
        { key: 'status', label: 'Statut' },
        { key: 'isObsolete', label: 'Obsolète', align: 'center' },
        { key: 'generatedAt', label: 'Générée le', mono: true },
    ];

    const state = useServerTableState<DeclarationFilters>({
        only: ['declarations', 'query'],
        initialPage: opts.query.page,
        initialPerPage: opts.query.perPage,
        initialSortKey: opts.query.sortKey,
        initialSortDirection: opts.query.sortDirection,
        defaultFilters: {
            companyId: null,
            fiscalYear: null,
            status: null,
            obsoleteOnly: false,
        },
        initialFilters: {
            companyId: opts.query.companyId,
            fiscalYear: opts.query.fiscalYear,
            status: opts.query.status,
            obsoleteOnly: opts.query.obsoleteOnly,
        },
        serializeFilters: (f) => ({
            companyId: f.companyId,
            fiscalYear: f.fiscalYear,
            status: f.status,
            obsoleteOnly: f.obsoleteOnly ? '1' : null,
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

    const activeFilterChips = computed<DeclarationFilterChip[]>(() => {
        const chips: DeclarationFilterChip[] = [];
        const f = state.filters.value;

        if (f.companyId !== null) {
            const c = opts.companyOptions.find((x) => x.id === f.companyId);
            chips.push({
                key: 'companyId',
                label: `Entreprise : ${c?.shortCode ?? '#' + f.companyId}`,
            });
        }

        if (f.fiscalYear !== null) {
            chips.push({ key: 'fiscalYear', label: `Année : ${f.fiscalYear}` });
        }

        if (f.status !== null) {
            const labels: Record<string, string> = {
                draft: 'Brouillon',
                deferred: 'Mise de côté',
                generated: 'Générée',
            };
            chips.push({ key: 'status', label: `Statut : ${labels[f.status] ?? f.status}` });
        }

        if (f.obsoleteOnly) {
            chips.push({ key: 'obsoleteOnly', label: 'Obsolètes uniquement' });
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

    function onRowClick(row: DeclarationRow): void {
        router.visit(declarationsShowRoute.url({ declaration: row.id }));
    }

    const companyIdModel = computed<number | null>({
        get: () => state.filters.value.companyId,
        set: (value) => state.setFilter('companyId', value),
    });

    const fiscalYearModel = computed<number | null>({
        get: () => state.filters.value.fiscalYear,
        set: (value) => state.setFilter('fiscalYear', value),
    });

    const statusModel = computed<App.Enums.FiscalDeclaration.FiscalDeclarationStatus | null>({
        get: () => state.filters.value.status,
        set: (value) => state.setFilter('status', value),
    });

    const obsoleteOnlyModel = computed<boolean>({
        get: () => state.filters.value.obsoleteOnly,
        set: (value) => state.setFilter('obsoleteOnly', value),
    });

    return {
        columns,
        state,
        activeSortColumnKey,
        activeFilterChips,
        activeFiltersCount,
        companyIdModel,
        fiscalYearModel,
        statusModel,
        obsoleteOnlyModel,
        onHeaderClick,
        onRowClick,
    };
}
