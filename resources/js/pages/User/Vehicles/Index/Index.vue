<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref, toRef } from 'vue';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import FieldLabel from '@/Components/Ui/FieldLabel/FieldLabel.vue';
import NumberInput from '@/Components/Ui/NumberInput/NumberInput.vue';
import SelectInput from '@/Components/Ui/SelectInput/SelectInput.vue';
import FilterChips from '@/Components/Ui/Table/FilterChips.vue';
import FilterPopover from '@/Components/Ui/Table/FilterPopover.vue';
import TextInput from '@/Components/Ui/TextInput/TextInput.vue';
import { useFiscalYear } from '@/Composables/Shared/useFiscalYear';
import {
    useFleetTable,
} from '@/Composables/Vehicle/Index/useFleetTable';
import type { FleetFilters } from '@/Composables/Vehicle/Index/useFleetTable';
import EmptyFleetState from './partials/EmptyFleetState.vue';
import FleetTable from './partials/FleetTable.vue';
import PageHeader from './partials/PageHeader.vue';

const props = defineProps<{
    vehicles: App.Data.User.Vehicle.VehicleListItemData[];
}>();

const { currentYear: fiscalYear } = useFiscalYear();
const filtersOpen = ref<boolean>(false);

const tableState = useFleetTable({
    vehicles: toRef(props, 'vehicles'),
    fiscalYear,
});

const statusOptions = [
    { value: 'active', label: 'Active' },
    { value: 'maintenance', label: 'Maintenance' },
    { value: 'sold', label: 'Vendu' },
    { value: 'destroyed', label: 'Détruit' },
    { value: 'other', label: 'Autre' },
];

const searchModel = computed({
    get: () => tableState.state.filters.value.search,
    set: (value: string) => {
        tableState.state.setFilter('search', value);
    },
});

const statusModel = computed({
    get: () => tableState.state.filters.value.status ?? '',
    set: (value: string | number) => {
        const v = String(value);
        tableState.state.setFilter(
            'status',
            v === 'active' || v === 'maintenance' || v === 'sold' || v === 'destroyed' || v === 'other'
                ? (v as App.Enums.Vehicle.VehicleStatus)
                : null,
        );
    },
});

const minModel = computed({
    get: () => tableState.state.filters.value.fullYearTaxMin,
    set: (value: number | null) => {
        tableState.state.setFilter('fullYearTaxMin', value);
    },
});

const maxModel = computed({
    get: () => tableState.state.filters.value.fullYearTaxMax,
    set: (value: number | null) => {
        tableState.state.setFilter('fullYearTaxMax', value);
    },
});
</script>

<template>
    <Head title="Flotte" />

    <UserLayout>
        <div class="flex flex-col gap-6">
            <PageHeader :fiscal-year="fiscalYear" />

            <EmptyFleetState v-if="props.vehicles.length === 0" />
            <template v-else>
                <div class="flex flex-wrap items-center gap-3">
                    <FilterChips
                        class="flex-1"
                        :chips="tableState.activeFilterChips.value"
                        @remove="(key: string) => tableState.removeFilter(key as keyof FleetFilters)"
                    />
                    <FilterPopover
                        class="ml-auto shrink-0"
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
                                    placeholder="Immatriculation, marque, modèle…"
                                />
                            </div>
                            <div>
                                <FieldLabel for="filter-status">Statut</FieldLabel>
                                <SelectInput
                                    id="filter-status"
                                    v-model="statusModel"
                                    placeholder="Tous les statuts"
                                    :options="statusOptions"
                                />
                            </div>
                            <div>
                                <FieldLabel for="filter-tax-min">Coût plein (€)</FieldLabel>
                                <div class="grid grid-cols-2 gap-2">
                                    <NumberInput
                                        id="filter-tax-min"
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

                <FleetTable
                    :vehicles="tableState.rows.value"
                    :columns="tableState.columns.value"
                    :sort-key="tableState.state.sort.value.key"
                    :sort-direction="tableState.state.sort.value.direction"
                    :status-label="tableState.statusLabel"
                    :status-dot-class="tableState.statusDotClass"
                    @sort="tableState.state.setSort"
                    @row-click="tableState.handleRowClick"
                />
            </template>
        </div>
    </UserLayout>
</template>
