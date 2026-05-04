<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import CheckboxInput from '@/Components/Ui/CheckboxInput/CheckboxInput.vue';
import FieldLabel from '@/Components/Ui/FieldLabel/FieldLabel.vue';
import Paginator from '@/Components/Ui/Paginator/Paginator.vue';
import SearchInput from '@/Components/Ui/SearchInput/SearchInput.vue';
import SelectInput from '@/Components/Ui/SelectInput/SelectInput.vue';
import FilterPopover from '@/Components/Ui/Table/FilterPopover.vue';
import { useFiscalYear } from '@/Composables/Shared/useFiscalYear';
import { useFleetTable } from '@/Composables/Vehicle/Index/useFleetTable';
import EmptyFleetState from './partials/EmptyFleetState.vue';
import FleetTable from './partials/FleetTable.vue';
import PageHeader from './partials/PageHeader.vue';

const props = defineProps<{
    vehicles: App.Data.User.Vehicle.PaginatedVehicleListData;
    query: App.Data.User.Vehicle.VehicleIndexQueryData;
}>();

const { currentYear: fiscalYear } = useFiscalYear();
const filtersOpen = ref<boolean>(false);

const tableState = useFleetTable({
    query: props.query,
    fiscalYear: fiscalYear.value,
});

// Computed wrapper sur le ref `state.search` (pattern fiable v-model).
const searchModel = computed<string>({
    get: () => tableState.state.search.value,
    set: (value: string) => {
        tableState.state.search.value = value;
    },
});

const statusOptions = [
    { value: 'active', label: 'Active' },
    { value: 'maintenance', label: 'Maintenance' },
    { value: 'sold', label: 'Vendu' },
    { value: 'destroyed', label: 'Détruit' },
    { value: 'other', label: 'Autre' },
];

const statusModel = computed<string | number>({
    get: () => tableState.state.filters.value.status ?? '',
    set: (value: string | number) => {
        const v = String(value);
        const isValid =
            v === 'active' ||
            v === 'maintenance' ||
            v === 'sold' ||
            v === 'destroyed' ||
            v === 'other';
        tableState.state.setFilter(
            'status',
            isValid ? (v as App.Enums.Vehicle.VehicleStatus) : null,
        );
    },
});

// CheckboxInput v-model bindé directement. Le watch interne du composable
// ne déclenche PAS de reload (pas dans search), mais on appelle
// setFilter() pour reset page=1 + reload immédiat.
const includeExitedModel = computed<boolean>({
    get: () => tableState.state.filters.value.includeExited,
    set: (value: boolean) => {
        tableState.state.setFilter('includeExited', value);
    },
});

const activeFiltersCount = computed<number>(() => {
    let n = 0;

    if (tableState.state.filters.value.status !== null) {
        n += 1;
    }

    // includeExited n'est pas compté comme "filtre actif" : c'est une
    // bascule de scope (visible directement à côté), pas un filtre dans
    // le popover.
    return n;
});
</script>

<template>
    <Head title="Flotte" />

    <UserLayout>
        <div class="flex flex-col gap-6">
            <PageHeader :fiscal-year="fiscalYear" />

            <div
                v-if="
                    vehicles.meta.total === 0 &&
                    searchModel === '' &&
                    tableState.state.filters.value.status === null &&
                    !tableState.state.filters.value.includeExited
                "
            >
                <EmptyFleetState />
            </div>

            <template v-else>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="max-w-md grow">
                            <SearchInput
                                v-model="searchModel"
                                placeholder="Rechercher (immat, marque, modèle)"
                                aria-label="Rechercher un véhicule"
                            />
                        </div>
                        <FilterPopover
                            v-model:open="filtersOpen"
                            :active-count="activeFiltersCount"
                            @reset="tableState.state.clearFilters"
                        >
                            <div class="flex flex-col gap-3">
                                <div>
                                    <FieldLabel for="filter-status"
                                        >Statut</FieldLabel
                                    >
                                    <SelectInput
                                        id="filter-status"
                                        v-model="statusModel"
                                        placeholder="Tous les statuts"
                                        :options="statusOptions"
                                    />
                                </div>
                            </div>
                        </FilterPopover>
                    </div>

                    <CheckboxInput
                        v-model="includeExitedModel"
                        label="Inclure les véhicules retirés"
                    />
                </div>

                <FleetTable
                    :vehicles="vehicles.data"
                    :columns="tableState.columns"
                    :active-sort-column-key="
                        tableState.activeSortColumnKey.value
                    "
                    :sort-direction="tableState.state.sort.value.direction"
                    @header-click="tableState.onHeaderClick"
                    @row-click="tableState.onRowClick"
                />

                <Paginator
                    :meta="vehicles.meta"
                    @page-change="tableState.state.setPage"
                    @per-page-change="tableState.state.setPerPage"
                />
            </template>
        </div>
    </UserLayout>
</template>
