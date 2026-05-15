<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
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

const props = defineProps<{
    vehicles: App.Data.User.Vehicle.PaginatedVehicleListData;
    options: {
        firstRegistrationYearBounds: { min: number; max: number } | null;
    };
    query: App.Data.User.Vehicle.VehicleIndexQueryData;
    /**
     * Année résolue par le backend pour les colonnes financières
     * (cf. `VehicleController::index`). Sert à initialiser le sélecteur
     * d'année local au premier rendu, puis pilote le label des colonnes
     * « Taxe pleine » / « Montant loyer ».
     */
    selectedYear: number;
    /**
     * Scope d'années dynamique calculé depuis les contrats actifs
     * (chantier η Phase 3). Remplace l'ancienne config statique
     * `floty.fiscal.available_years` qui était lue via `useFiscalYear`.
     * Bornes : `[minYear..max(currentYear, maxContractYear)]`.
     */
    yearScope: App.Data.Shared.YearScopeData;
    /**
     * `true` ssi au moins un véhicule existe en base. Source de vérité
     * unique pour décider du placeholder « Aucun véhicule ». Évite le
     * flash placeholder lors du reset de filtre, et le faux-positif
     * quand toute la flotte est retirée et `showExited=false`.
     */
    hasAnyVehicle: boolean;
}>();

const filtersOpen = ref<boolean>(false);

// Année calendaire courante (front) · distincte de `selectedYear` qui
// est pilotée par l'utilisateur. Sert de borne de référence dans
// `useFleetTable` (ex. pour afficher des badges « En cours »).
const currentRealYear = new Date().getFullYear();

const tableState = useFleetTable({
    query: props.query,
    selectedYear: props.selectedYear,
    currentRealYear,
});

const availableYears = computed<readonly number[]>(() => props.yearScope.availableYears);
const {
    searchModel,
    selectedYearModel,
    statusModel,
    energySourceModel,
    pollutantCategoryModel,
    handicapAccessModel,
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
