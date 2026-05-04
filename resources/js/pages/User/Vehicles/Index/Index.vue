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
    query: App.Data.User.Vehicle.VehicleIndexQueryData;
}>();

const { currentYear: fiscalYear, availableYears } = useFiscalYear();
const filtersOpen = ref<boolean>(false);

// Bornes de l'année d'acquisition : min = 1ère année fiscale configurée
// (typiquement 2024), max = année calendaire courante. `new Date()` est
// volontairement utilisé ici car il s'agit d'une borne UI de sélecteur,
// pas de logique fiscale (laquelle passe systématiquement par useFiscalYear).
const acquisitionYearOptions = computed<{ value: number; label: string }[]>(
    () => {
        const min = availableYears.value[0] ?? 2024;
        const max = new Date().getFullYear();
        const options: { value: number; label: string }[] = [];

        for (let year = max; year >= min; year--) {
            options.push({ value: year, label: String(year) });
        }

        return options;
    },
);

const tableState = useFleetTable({
    query: props.query,
    fiscalYear: fiscalYear.value,
});

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

function parseYear(value: string | number | null): number | null {
    if (value === null || value === '' || value === undefined) {
        return null;
    }

    const n = typeof value === 'number' ? value : Number.parseInt(value, 10);

    return Number.isNaN(n) ? null : n;
}

const acquisitionMinModel = computed<string | number | null>({
    get: () => tableState.state.filters.value.acquisitionYearMin ?? '',
    set: (value: string | number | null) => {
        tableState.state.setFilter('acquisitionYearMin', parseYear(value));
    },
});

const acquisitionMaxModel = computed<string | number | null>({
    get: () => tableState.state.filters.value.acquisitionYearMax ?? '',
    set: (value: string | number | null) => {
        tableState.state.setFilter('acquisitionYearMax', parseYear(value));
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

    if (f.includeExited) {
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

    if (f.acquisitionYearMin !== null || f.acquisitionYearMax !== null) {
        n += 1;
    }

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
                    activeFiltersCount === 0
                "
            >
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
                            <div>
                                <FieldLabel for="filter-acquisition-min"
                                    >Année d'acquisition</FieldLabel
                                >
                                <div class="grid grid-cols-2 gap-2">
                                    <SelectInput
                                        id="filter-acquisition-min"
                                        v-model="acquisitionMinModel"
                                        placeholder="Min"
                                        :options="acquisitionYearOptions"
                                        nullable
                                    />
                                    <SelectInput
                                        v-model="acquisitionMaxModel"
                                        placeholder="Max"
                                        :options="acquisitionYearOptions"
                                        nullable
                                    />
                                </div>
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
