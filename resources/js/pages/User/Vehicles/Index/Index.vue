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
import YearRangeGridPicker from '@/Components/Ui/YearRangeGridPicker/YearRangeGridPicker.vue';
import { useFiscalYear } from '@/Composables/Shared/useFiscalYear';
import { useFleetTable } from '@/Composables/Vehicle/Index/useFleetTable';
import {
    energySourceLabel,
    pollutantCategoryLabel,
    vehicleStatusLabel,
} from '@/Utils/labels/vehicleEnumLabels';
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
     * « Coût plein » / « Prix location ».
     */
    selectedYear: number;
    /**
     * `true` ssi au moins un véhicule existe en base. Source de vérité
     * unique pour décider du placeholder « Aucun véhicule ». Évite le
     * flash placeholder lors du reset de filtre, et le faux-positif
     * quand toute la flotte est retirée et `showExited=false`.
     */
    hasAnyVehicle: boolean;
}>();

const { availableYears } = useFiscalYear();
const filtersOpen = ref<boolean>(false);

// Année calendaire courante (front) — distincte de `selectedYear` qui
// est pilotée par l'utilisateur. Sert de borne de référence dans
// `useFleetTable` (ex. pour afficher des badges « En cours »).
const currentRealYear = new Date().getFullYear();

const tableState = useFleetTable({
    query: props.query,
    selectedYear: props.selectedYear,
    currentRealYear,
});

// Sélecteur d'année local à la page (chantier η anticipé) — pilote
// uniquement les colonnes financières. Le label explicite évite la
// confusion d'un sélecteur global anonyme (cf. arbitrage chantier θ.4).
const selectedYearModel = computed<number>({
    get: () => tableState.state.filters.value.year,
    set: (value: number) => {
        tableState.state.setFilter('year', value);
    },
});

const yearOptions = computed<{ value: number; label: string }[]>(() =>
    availableYears.value.map((year) => ({ value: year, label: String(year) })),
);

const searchModel = computed<string>({
    get: () => tableState.state.search.value,
    set: (value: string) => {
        tableState.state.search.value = value;
    },
});

const statusOptions = (
    Object.keys(vehicleStatusLabel) as App.Enums.Vehicle.VehicleStatus[]
).map((value) => ({ value, label: vehicleStatusLabel[value] }));

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

const energySourceOptions = (
    Object.keys(energySourceLabel) as App.Enums.Vehicle.EnergySource[]
).map((value) => ({ value, label: energySourceLabel[value] }));

const energySourceModel = computed<string | number>({
    get: () => tableState.state.filters.value.energySource ?? '',
    set: (value: string | number) => {
        const v = String(value);
        const allowed = Object.keys(
            energySourceLabel,
        ) as App.Enums.Vehicle.EnergySource[];
        const next = allowed.includes(v as App.Enums.Vehicle.EnergySource)
            ? (v as App.Enums.Vehicle.EnergySource)
            : null;
        tableState.state.setFilter('energySource', next);
    },
});

const pollutantCategoryOptions = (
    Object.keys(
        pollutantCategoryLabel,
    ) as App.Enums.Vehicle.PollutantCategory[]
).map((value) => ({ value, label: pollutantCategoryLabel[value] }));

const pollutantCategoryModel = computed<string | number>({
    get: () => tableState.state.filters.value.pollutantCategory ?? '',
    set: (value: string | number) => {
        const v = String(value);
        const allowed = Object.keys(
            pollutantCategoryLabel,
        ) as App.Enums.Vehicle.PollutantCategory[];
        const next = allowed.includes(v as App.Enums.Vehicle.PollutantCategory)
            ? (v as App.Enums.Vehicle.PollutantCategory)
            : null;
        tableState.state.setFilter('pollutantCategory', next);
    },
});

const handicapAccessModel = computed<boolean>({
    get: () => tableState.state.filters.value.handicapAccess === true,
    set: (value: boolean) => {
        tableState.state.setFilter('handicapAccess', value === true ? true : null);
    },
});

const firstRegistrationYearMinModel = computed<number | null>({
    get: () => tableState.state.filters.value.firstRegistrationYearMin,
    set: (value: number | null) => {
        tableState.state.setFilter('firstRegistrationYearMin', value);
    },
});

const firstRegistrationYearMaxModel = computed<number | null>({
    get: () => tableState.state.filters.value.firstRegistrationYearMax,
    set: (value: number | null) => {
        tableState.state.setFilter('firstRegistrationYearMax', value);
    },
});

const includeExitedModel = computed<boolean>({
    get: () => tableState.state.filters.value.includeExited,
    set: (value: boolean) => {
        tableState.state.setFilter('includeExited', value);
    },
});

const activeFiltersCount = computed<number>(() => {
    let n = 0;
    const f = tableState.state.filters.value;

    if (f.status !== null) {
        n += 1;
    }

    // includeExited défaut true : compté comme filtre actif uniquement
    // si l'utilisateur a explicitement décoché (override = exclure).
    if (!f.includeExited) {
        n += 1;
    }

    if (f.energySource !== null) {
        n += 1;
    }

    if (f.pollutantCategory !== null) {
        n += 1;
    }

    if (f.handicapAccess === true) {
        n += 1;
    }

    if (
        f.firstRegistrationYearMin !== null ||
        f.firstRegistrationYearMax !== null
    ) {
        n += 1;
    }

    return n;
});
</script>

<template>
    <Head title="Flotte" />

    <UserLayout>
        <div class="flex flex-col gap-6">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <PageHeader :fiscal-year="props.selectedYear" />
                <div class="flex flex-col gap-1">
                    <FieldLabel for="fleet-financial-year">
                        Année des colonnes financières
                    </FieldLabel>
                    <SelectInput
                        id="fleet-financial-year"
                        v-model.number="selectedYearModel"
                        :options="yearOptions"
                        :disabled="yearOptions.length <= 1"
                    />
                </div>
            </div>

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
                                    placeholder="Tous les statuts"
                                    :options="statusOptions"
                                    nullable
                                />
                            </div>
                            <div>
                                <FieldLabel for="filter-energy"
                                    >Énergie</FieldLabel
                                >
                                <SelectInput
                                    id="filter-energy"
                                    v-model="energySourceModel"
                                    placeholder="Toutes les énergies"
                                    :options="energySourceOptions"
                                    nullable
                                />
                            </div>
                            <div>
                                <FieldLabel for="filter-pollutant"
                                    >Catégorie polluant</FieldLabel
                                >
                                <SelectInput
                                    id="filter-pollutant"
                                    v-model="pollutantCategoryModel"
                                    placeholder="Toutes catégories"
                                    :options="pollutantCategoryOptions"
                                    nullable
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
