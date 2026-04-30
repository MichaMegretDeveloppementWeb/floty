import { computed } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import { useTableState } from '@/Composables/Shared/useTableState';
import type { TableState } from '@/Composables/Shared/useTableState';
import type { DataTableColumn } from '@/types/ui';

type CompanyRow = App.Data.User.Company.CompanyListItemData;

export type CompanyFilters = {
    search: string;
    isActive: 'yes' | 'no' | null;
    daysUsedMin: number | null;
    daysUsedMax: number | null;
};

export type CompanySortKey = 'company' | 'siren' | 'city' | 'days' | 'tax';

const DEFAULT_FILTERS: CompanyFilters = {
    search: '',
    isActive: null,
    daysUsedMin: null,
    daysUsedMax: null,
};

const SORT_KEYS: readonly CompanySortKey[] = [
    'company',
    'siren',
    'city',
    'days',
    'tax',
];

export type CompanyFilterChip = {
    key: keyof CompanyFilters;
    label: string;
};

export function useCompaniesTable(opts: {
    companies: Ref<readonly CompanyRow[]>;
    fiscalYear: Ref<number>;
}): {
    columns: ComputedRef<readonly DataTableColumn<CompanyRow>[]>;
    rows: ComputedRef<CompanyRow[]>;
    state: TableState<CompanyRow, CompanyFilters, CompanySortKey>;
    activeFilterChips: ComputedRef<CompanyFilterChip[]>;
    removeFilter: (key: keyof CompanyFilters) => void;
} {
    const columns = computed<readonly DataTableColumn<CompanyRow>[]>(() => [
        { key: 'company', label: 'Entreprise' },
        { key: 'siren', label: 'SIREN', mono: true },
        { key: 'city', label: 'Ville' },
        { key: 'daysUsed', label: `Jours ${opts.fiscalYear.value}`, mono: true },
        { key: 'annualTaxDue', label: `Taxe ${opts.fiscalYear.value}` },
    ]);

    const state = useTableState<CompanyRow, CompanyFilters, CompanySortKey>({
        defaultFilters: DEFAULT_FILTERS,
        sortKeys: SORT_KEYS,
        parseFiltersFromUrl: (params) => ({
            search: params.get('search') ?? '',
            isActive: parseIsActive(params.get('isActive')),
            daysUsedMin: parseIntOrNull(params.get('daysUsedMin')),
            daysUsedMax: parseIntOrNull(params.get('daysUsedMax')),
        }),
        serializeFiltersToUrl: (f) => ({
            search: f.search === '' ? null : f.search,
            isActive: f.isActive,
            daysUsedMin: f.daysUsedMin?.toString() ?? null,
            daysUsedMax: f.daysUsedMax?.toString() ?? null,
        }),
        applyFilter: (item, f) => {
            if (f.search !== '') {
                const needle = f.search.toLowerCase();
                const haystack =
                    `${item.legalName} ${item.shortCode} ${item.siren ?? ''} ${item.city ?? ''}`.toLowerCase();

                if (!haystack.includes(needle)) {
                    return false;
                }
            }

            if (f.isActive === 'yes' && !item.isActive) {
                return false;
            }

            if (f.isActive === 'no' && item.isActive) {
                return false;
            }

            if (f.daysUsedMin !== null && item.daysUsed < f.daysUsedMin) {
                return false;
            }

            if (f.daysUsedMax !== null && item.daysUsed > f.daysUsedMax) {
                return false;
            }

            return true;
        },
        sortComparators: {
            company: (a, b) => a.legalName.localeCompare(b.legalName),
            siren: (a, b) => (a.siren ?? '').localeCompare(b.siren ?? ''),
            city: (a, b) => (a.city ?? '').localeCompare(b.city ?? ''),
            days: (a, b) => a.daysUsed - b.daysUsed,
            tax: (a, b) => a.annualTaxDue - b.annualTaxDue,
        },
    });

    const rows = computed<CompanyRow[]>(() => state.apply(opts.companies.value));

    const activeFilterChips = computed<CompanyFilterChip[]>(() => {
        const chips: CompanyFilterChip[] = [];
        const f = state.filters.value;

        if (f.search !== '') {
            chips.push({ key: 'search', label: `Recherche : « ${f.search} »` });
        }

        if (f.isActive !== null) {
            chips.push({
                key: 'isActive',
                label: f.isActive === 'yes' ? 'Active' : 'Inactive',
            });
        }

        if (f.daysUsedMin !== null || f.daysUsedMax !== null) {
            const min = f.daysUsedMin === null ? '…' : `${f.daysUsedMin} j`;
            const max = f.daysUsedMax === null ? '…' : `${f.daysUsedMax} j`;
            chips.push({
                key: 'daysUsedMin',
                label: `Jours utilisés : ${min} → ${max}`,
            });
        }

        return chips;
    });

    function removeFilter(key: keyof CompanyFilters): void {
        if (key === 'daysUsedMin' || key === 'daysUsedMax') {
            state.setFilter('daysUsedMin', null);
            state.setFilter('daysUsedMax', null);

            return;
        }

        state.setFilter(key, DEFAULT_FILTERS[key]);
    }

    return {
        columns,
        rows,
        state,
        activeFilterChips,
        removeFilter,
    };
}

function parseIsActive(value: string | null): 'yes' | 'no' | null {
    if (value === 'yes' || value === 'no') {
        return value;
    }

    return null;
}

function parseIntOrNull(value: string | null): number | null {
    if (value === null) {
        return null;
    }

    const n = Number.parseInt(value, 10);

    return Number.isNaN(n) ? null : n;
}
