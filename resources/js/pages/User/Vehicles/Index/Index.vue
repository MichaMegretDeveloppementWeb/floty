<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import CheckboxInput from '@/Components/Ui/CheckboxInput/CheckboxInput.vue';
import FieldLabel from '@/Components/Ui/FieldLabel/FieldLabel.vue';
import InlineYearSelector from '@/Components/Ui/InlineYearSelector/InlineYearSelector.vue';
import Paginator from '@/Components/Ui/Paginator/Paginator.vue';
import SearchInput from '@/Components/Ui/SearchInput/SearchInput.vue';
import SelectInput from '@/Components/Ui/SelectInput/SelectInput.vue';
import FilterPopover from '@/Components/Ui/Table/FilterPopover.vue';
import YearRangeGridPicker from '@/Components/Ui/YearRangeGridPicker/YearRangeGridPicker.vue';
import { useFleetTable } from '@/Composables/Vehicle/Index/useFleetTable';
import { useVehiclesIndexFilters } from '@/Composables/Vehicle/Index/useVehiclesIndexFilters';
import EmptyFleetState from './partials/EmptyFleetState.vue';
import FleetTable from './partials/FleetTable.vue';
import PageHeader from './partials/PageHeader.vue';

/**
 * Per-vehicle financial figures served via Inertia::defer.
 * `VehicleListItemData` is rendered with the 3 financial fields nulled on
 * first paint, this map fills the cells on the second round-trip.
 */
type VehicleCosts = Record<
    number,
    { fullYearTax: number; dailyTaxRate: number; rentalPriceFullYear: number | null }
>;

/**
 * Per-vehicle regulatory-control badge, served eager. SPARSE: only vehicles
 * with at least one control needing attention appear in the map, so the table
 * renders a badge only where there is one. Today-based, recomputed server-side
 * on every list visit (sort / filter / page).
 */
type ControlsBadges = Record<number, App.Data.User.Control.Vehicle.VehicleControlsBadgeData>;

const props = defineProps<{
    vehicles: App.Data.User.Vehicle.PaginatedVehicleListData;
    vehiclesCosts?: VehicleCosts;
    controlsBadges: ControlsBadges;
    options: {
        firstRegistrationYearBounds: { min: number; max: number } | null;
    };
    query: App.Data.User.Vehicle.VehicleIndexQueryData;
    selectedYear: number;
    yearScope: App.Data.Shared.YearScopeData;
    hasAnyVehicle: boolean;
}>();

const filtersOpen = ref<boolean>(false);

const currentRealYear = new Date().getFullYear();

const tableState = useFleetTable({
    query: props.query,
    selectedYear: props.selectedYear,
    currentRealYear,
});

// Local mirror of `vehiclesCosts` reset to undefined before each reload
// so cells fall back to a skeleton. Inertia preserves deferred props on
// partial reloads, so without this mirror stale values would flash for
// the duration of the manual reload RTT.
const localVehiclesCosts = ref<VehicleCosts | undefined>(props.vehiclesCosts);

watch(
    () => props.vehiclesCosts,
    (next) => {
        localVehiclesCosts.value = next;
    },
);

// Re-fetch costs whenever the selected year OR the visible vehicle IDs
// change. The internal `router.get` from `useServerTableState` only
// reloads `['vehicles', 'query', 'selectedYear']`, so cells would
// otherwise stay frozen on year change.
watch(
    () => `${props.selectedYear}|${props.vehicles.data.map((v) => v.id).join(',')}`,
    () => {
        localVehiclesCosts.value = undefined;
        router.reload({ only: ['vehiclesCosts'] });
    },
);

const availableYears = computed<readonly number[]>(() => props.yearScope.availableYears);
const {
    searchModel,
    selectedYearModel,
    statusModel,
    energySourceModel,
    pollutantCategoryModel,
    handicapAccessModel,
    controlsDueModel,
    firstRegistrationYearMinModel,
    firstRegistrationYearMaxModel,
    includeExitedModel,
    statusOptions,
    energySourceOptions,
    pollutantCategoryOptions,
    yearOptions,
    activeFiltersCount,
} = useVehiclesIndexFilters({
    tableState: tableState.state,
    availableYears,
});
</script>

<template>
    <Head title="Flotte" />

    <UserLayout>
        <div class="flex flex-col gap-6">
            <PageHeader :fiscal-year="props.selectedYear" />

            <div v-if="!props.hasAnyVehicle">
                <EmptyFleetState />
            </div>

            <template v-else>
                <div class="flex flex-wrap items-center gap-3">
                    <div class="grow max-w-md">
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
                                    :options="statusOptions"
                                />
                            </div>
                            <div>
                                <FieldLabel for="filter-energy"
                                    >Énergie</FieldLabel
                                >
                                <SelectInput
                                    id="filter-energy"
                                    v-model="energySourceModel"
                                    :options="energySourceOptions"
                                />
                            </div>
                            <div>
                                <FieldLabel for="filter-pollutant"
                                    >Catégorie polluant</FieldLabel
                                >
                                <SelectInput
                                    id="filter-pollutant"
                                    v-model="pollutantCategoryModel"
                                    :options="pollutantCategoryOptions"
                                />
                            </div>
                            <div v-if="options.firstRegistrationYearBounds">
                                <FieldLabel for="filter-first-registration"
                                    >Année de 1ʳᵉ immatriculation</FieldLabel
                                >
                                <YearRangeGridPicker
                                    v-model:year-min="
                                        firstRegistrationYearMinModel
                                    "
                                    v-model:year-max="
                                        firstRegistrationYearMaxModel
                                    "
                                    :min="options.firstRegistrationYearBounds.min"
                                    :max="options.firstRegistrationYearBounds.max"
                                />
                            </div>
                            <div>
                                <CheckboxInput
                                    v-model="handicapAccessModel"
                                    label="Accès handicapé uniquement"
                                />
                            </div>
                            <div>
                                <CheckboxInput
                                    v-model="controlsDueModel"
                                    label="Contrôle à échéance uniquement"
                                />
                            </div>
                            <div>
                                <CheckboxInput
                                    v-model="includeExitedModel"
                                    label="Inclure les véhicules retirés"
                                />
                            </div>
                        </div>
                    </FilterPopover>
                    <div class="ml-auto">
                        <InlineYearSelector
                            id="fleet-financial-year"
                            v-model="selectedYearModel"
                            label="Colonnes financières"
                            :options="yearOptions"
                            :disabled="yearOptions.length <= 1"
                        />
                    </div>
                </div>

                <FleetTable
                    :vehicles="vehicles.data"
                    :costs="localVehiclesCosts"
                    :controls-badges="controlsBadges"
                    :columns="tableState.columns.value"
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
