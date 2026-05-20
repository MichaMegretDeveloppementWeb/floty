<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import FieldLabel from '@/Components/Ui/FieldLabel/FieldLabel.vue';
import InlineYearSelector from '@/Components/Ui/InlineYearSelector/InlineYearSelector.vue';
import Paginator from '@/Components/Ui/Paginator/Paginator.vue';
import SearchInput from '@/Components/Ui/SearchInput/SearchInput.vue';
import SelectInput from '@/Components/Ui/SelectInput/SelectInput.vue';
import FilterPopover from '@/Components/Ui/Table/FilterPopover.vue';
import TextInput from '@/Components/Ui/TextInput/TextInput.vue';
import { useCompaniesTable } from '@/Composables/Company/Index/useCompaniesTable';
import CompaniesTable from './partials/CompaniesTable.vue';
import EmptyCompaniesState from './partials/EmptyCompaniesState.vue';
import PageHeader from './partials/PageHeader.vue';

const props = defineProps<{
    companies: App.Data.User.Company.PaginatedCompanyListData;
    query: App.Data.User.Company.CompanyIndexQueryData;
    hasAnyCompany: boolean;
    selectedYear: number;
    yearScope: App.Data.Shared.YearScopeData;
    // Map keyed by companyId. Not picked up by the TS Transformer (Spatie
    // Data does not emit indexed arrays), so the type stays manual.
    costs?: Record<
        number,
        { annualTaxDue: number; rentalPriceTotal: number | null }
    >;
}>();

const availableYears = computed<readonly number[]>(() => props.yearScope.availableYears);
const filtersOpen = ref<boolean>(false);

const tableState = useCompaniesTable({
    query: props.query,
    selectedYear: props.selectedYear,
});

// Local mirror of `costs` reset to undefined before each reload so the
// skeleton fallback is visible. Inertia preserves deferred props across
// partial reloads, so without this mirror stale values would flash for
// the duration of the manual reload RTT.
const localCosts = ref<typeof props.costs>(props.costs);

watch(
    () => props.costs,
    (next) => {
        localCosts.value = next;
    },
);

// Re-fetch costs whenever the selected year OR the visible company IDs
// change. The internal `router.get` from `useServerTableState` only
// reloads `['companies', 'query', 'selectedYear']`, so cells would
// otherwise stay frozen on year change.
watch(
    () => `${props.selectedYear}|${props.companies.data.map((c) => c.id).join(',')}`,
    () => {
        localCosts.value = undefined;
        router.reload({ only: ['costs'] });
    },
);

const yearOptions = computed<{ value: number; label: string }[]>(() =>
    availableYears.value.map((year) => ({ value: year, label: String(year) })),
);

const yearModel = computed<number>({
    get: () => tableState.state.filters.value.year,
    set: (v) => tableState.state.setFilter('year', v),
});

const searchModel = computed<string>({
    get: () => tableState.state.search.value,
    set: (value: string) => {
        tableState.state.search.value = value;
    },
});

const isActiveOptions = [
    { value: '', label: 'Toutes' },
    { value: 'yes', label: 'Active' },
    { value: 'no', label: 'Inactive' },
];

const isActiveModel = computed<string | number>({
    get: () => {
        if (tableState.state.filters.value.isActive === null) {
            return '';
        }

        return tableState.state.filters.value.isActive ? 'yes' : 'no';
    },
    set: (value: string | number) => {
        const v = String(value);
        const next = v === 'yes' ? true : v === 'no' ? false : null;
        tableState.state.setFilter('isActive', next);
    },
});

const contractsScopeOptions = [
    { value: '', label: 'Toutes' },
    { value: 'with', label: 'Avec locations' },
    { value: 'without', label: 'Sans locations' },
];

const contractsScopeModel = computed<string | number>({
    get: () => tableState.state.filters.value.contractsScope ?? '',
    set: (value: string | number) => {
        const v = String(value);
        tableState.state.setFilter(
            'contractsScope',
            v === 'with' || v === 'without' ? v : null,
        );
    },
});

const cityModel = computed<string>({
    get: () => tableState.state.filters.value.city ?? '',
    set: (value: string) => {
        tableState.state.setFilter('city', value === '' ? null : value);
    },
});

const activeFiltersCount = computed<number>(() => {
    let n = 0;
    const f = tableState.state.filters.value;

    if (f.isActive !== null) {
        n += 1;
    }

    if (f.contractsScope !== null) {
        n += 1;
    }

    if (f.city !== null && f.city !== '') {
        n += 1;
    }

    return n;
});
</script>

<template>
    <Head title="Entreprises" />

    <UserLayout>
        <div class="flex flex-col gap-6">
            <PageHeader :fiscal-year="props.selectedYear" />

            <div v-if="!props.hasAnyCompany">
                <EmptyCompaniesState />
            </div>

            <template v-else>
                <div class="flex flex-wrap items-center gap-3">
                    <div class="grow max-w-md">
                        <SearchInput
                            v-model="searchModel"
                            placeholder="Rechercher (nom, SIREN, code court)"
                            aria-label="Rechercher une entreprise"
                        />
                    </div>
                    <FilterPopover
                        v-model:open="filtersOpen"
                        :active-count="activeFiltersCount"
                        @reset="tableState.state.clearFilters"
                    >
                        <div class="flex flex-col gap-3">
                            <div>
                                <FieldLabel for="filter-active"
                                    >Activité</FieldLabel
                                >
                                <SelectInput
                                    id="filter-active"
                                    v-model="isActiveModel"
                                    :options="isActiveOptions"
                                />
                            </div>
                            <div>
                                <FieldLabel for="filter-contracts"
                                    >Locations</FieldLabel
                                >
                                <SelectInput
                                    id="filter-contracts"
                                    v-model="contractsScopeModel"
                                    :options="contractsScopeOptions"
                                />
                            </div>
                            <div>
                                <FieldLabel for="filter-city">Ville</FieldLabel>
                                <TextInput
                                    id="filter-city"
                                    v-model="cityModel"
                                    placeholder="Lyon, Paris…"
                                />
                            </div>
                        </div>
                    </FilterPopover>
                    <div class="ml-auto">
                        <InlineYearSelector
                            id="companies-year"
                            v-model="yearModel"
                            label="Colonnes financières"
                            :options="yearOptions"
                            :disabled="yearOptions.length <= 1"
                        />
                    </div>
                </div>

                <CompaniesTable
                    :companies="companies.data"
                    :costs="localCosts"
                    :columns="tableState.columns.value"
                    :active-sort-column-key="
                        tableState.activeSortColumnKey.value
                    "
                    :sort-direction="tableState.state.sort.value.direction"
                    @header-click="tableState.onHeaderClick"
                    @row-click="tableState.onRowClick"
                />

                <Paginator
                    :meta="companies.meta"
                    @page-change="tableState.state.setPage"
                    @per-page-change="tableState.state.setPerPage"
                />
            </template>
        </div>
    </UserLayout>
</template>
