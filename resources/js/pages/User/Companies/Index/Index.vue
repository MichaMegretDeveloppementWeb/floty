<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref, toRef } from 'vue';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import FieldLabel from '@/Components/Ui/FieldLabel/FieldLabel.vue';
import NumberInput from '@/Components/Ui/NumberInput/NumberInput.vue';
import SelectInput from '@/Components/Ui/SelectInput/SelectInput.vue';
import FilterPopover from '@/Components/Ui/Table/FilterPopover.vue';
import TextInput from '@/Components/Ui/TextInput/TextInput.vue';
import { useCompaniesTable } from '@/Composables/Company/Index/useCompaniesTable';
import { useFiscalYear } from '@/Composables/Shared/useFiscalYear';
import CompaniesTable from './partials/CompaniesTable.vue';
import EmptyCompaniesState from './partials/EmptyCompaniesState.vue';
import PageHeader from './partials/PageHeader.vue';

const props = defineProps<{
    companies: App.Data.User.Company.CompanyListItemData[];
}>();

const { currentYear: fiscalYear } = useFiscalYear();
const filtersOpen = ref<boolean>(false);

const tableState = useCompaniesTable({
    companies: toRef(props, 'companies'),
    fiscalYear,
});

const isActiveOptions = [
    { value: 'yes', label: 'Active' },
    { value: 'no', label: 'Inactive' },
];

const searchModel = computed({
    get: () => tableState.state.filters.value.search,
    set: (value: string) => {
        tableState.state.setFilter('search', value);
    },
});

const isActiveModel = computed({
    get: () => tableState.state.filters.value.isActive ?? '',
    set: (value: string | number) => {
        const v = String(value);
        tableState.state.setFilter('isActive', v === 'yes' || v === 'no' ? v : null);
    },
});

const minModel = computed({
    get: () => tableState.state.filters.value.daysUsedMin,
    set: (value: number | null) => {
        tableState.state.setFilter('daysUsedMin', value);
    },
});

const maxModel = computed({
    get: () => tableState.state.filters.value.daysUsedMax,
    set: (value: number | null) => {
        tableState.state.setFilter('daysUsedMax', value);
    },
});
</script>

<template>
    <Head title="Entreprises" />

    <UserLayout>
        <div class="flex flex-col gap-6">
            <PageHeader :fiscal-year="fiscalYear" />

            <EmptyCompaniesState v-if="props.companies.length === 0" />
            <template v-else>
                <div class="flex justify-end">
                    <FilterPopover
                        v-model:open="filtersOpen"
                        :active-count="tableState.state.activeFiltersCount.value"
                        @reset="tableState.state.clearFilters"
                    >
                        <div class="flex flex-col gap-3">
                            <div>
                                <FieldLabel for="filter-search">Recherche</FieldLabel>
                                <TextInput
                                    id="filter-search"
                                    v-model="searchModel"
                                    placeholder="Nom, SIREN, ville…"
                                />
                            </div>
                            <div>
                                <FieldLabel for="filter-active">Activité</FieldLabel>
                                <SelectInput
                                    id="filter-active"
                                    v-model="isActiveModel"
                                    placeholder="Toutes"
                                    :options="isActiveOptions"
                                />
                            </div>
                            <div>
                                <FieldLabel for="filter-days-min">Jours utilisés</FieldLabel>
                                <div class="grid grid-cols-2 gap-2">
                                    <NumberInput
                                        id="filter-days-min"
                                        v-model="minModel"
                                        placeholder="Min"
                                    />
                                    <NumberInput
                                        v-model="maxModel"
                                        placeholder="Max"
                                    />
                                </div>
                            </div>
                        </div>
                    </FilterPopover>
                </div>

                <CompaniesTable
                    :companies="tableState.rows.value"
                    :columns="tableState.columns.value"
                    :sort-key="tableState.state.sort.value.key"
                    :sort-direction="tableState.state.sort.value.direction"
                    @sort="tableState.state.setSort"
                />
            </template>
        </div>
    </UserLayout>
</template>
