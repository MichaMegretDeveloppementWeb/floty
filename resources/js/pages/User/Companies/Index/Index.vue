<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import FieldLabel from '@/Components/Ui/FieldLabel/FieldLabel.vue';
import Paginator from '@/Components/Ui/Paginator/Paginator.vue';
import SearchInput from '@/Components/Ui/SearchInput/SearchInput.vue';
import SelectInput from '@/Components/Ui/SelectInput/SelectInput.vue';
import FilterPopover from '@/Components/Ui/Table/FilterPopover.vue';
import TextInput from '@/Components/Ui/TextInput/TextInput.vue';
import { useCompaniesTable } from '@/Composables/Company/Index/useCompaniesTable';
import { useFiscalYear } from '@/Composables/Shared/useFiscalYear';
import CompaniesTable from './partials/CompaniesTable.vue';
import EmptyCompaniesState from './partials/EmptyCompaniesState.vue';
import PageHeader from './partials/PageHeader.vue';

const props = defineProps<{
    companies: App.Data.User.Company.PaginatedCompanyListData;
    query: App.Data.User.Company.CompanyIndexQueryData;
}>();

const { currentYear: fiscalYear } = useFiscalYear();
const filtersOpen = ref<boolean>(false);

const tableState = useCompaniesTable({
    query: props.query,
    fiscalYear: fiscalYear.value,
});

const searchModel = computed<string>({
    get: () => tableState.state.search.value,
    set: (value: string) => {
        tableState.state.search.value = value;
    },
});

const isActiveOptions = [
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
    { value: 'with', label: 'Avec contrats' },
    { value: 'without', label: 'Sans contrats' },
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

const companyTypeOptions = [
    { value: 'corporate', label: 'Personne morale' },
    { value: 'individual', label: 'Entrepreneur individuel' },
];

const companyTypeModel = computed<string | number>({
    get: () => tableState.state.filters.value.companyType ?? '',
    set: (value: string | number) => {
        const v = String(value);
        tableState.state.setFilter(
            'companyType',
            v === 'corporate' || v === 'individual' ? v : null,
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

    if (f.companyType !== null) {
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
            <PageHeader :fiscal-year="fiscalYear" />

            <div
                v-if="
                    companies.meta.total === 0 &&
                    searchModel === '' &&
                    activeFiltersCount === 0
                "
            >
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
                                    placeholder="Toutes"
                                    :options="isActiveOptions"
                                    nullable
                                />
                            </div>
                            <div>
                                <FieldLabel for="filter-contracts"
                                    >Contrats</FieldLabel
                                >
                                <SelectInput
                                    id="filter-contracts"
                                    v-model="contractsScopeModel"
                                    placeholder="Toutes"
                                    :options="contractsScopeOptions"
                                    nullable
                                />
                            </div>
                            <div>
                                <FieldLabel for="filter-type"
                                    >Type juridique</FieldLabel
                                >
                                <SelectInput
                                    id="filter-type"
                                    v-model="companyTypeModel"
                                    placeholder="Tous"
                                    :options="companyTypeOptions"
                                    nullable
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
                </div>

                <CompaniesTable
                    :companies="companies.data"
                    :columns="tableState.columns"
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
