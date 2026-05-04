<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import DateRangePicker from '@/Components/Ui/DateRangePicker/DateRangePicker.vue';
import FieldLabel from '@/Components/Ui/FieldLabel/FieldLabel.vue';
import Paginator from '@/Components/Ui/Paginator/Paginator.vue';
import SearchableSelect from '@/Components/Ui/SearchableSelect/SearchableSelect.vue';
import SearchInput from '@/Components/Ui/SearchInput/SearchInput.vue';
import SelectInput from '@/Components/Ui/SelectInput/SelectInput.vue';
import FilterPopover from '@/Components/Ui/Table/FilterPopover.vue';
import { useContractsTable } from '@/Composables/Contract/Index/useContractsTable';
import { useFiscalYear } from '@/Composables/Shared/useFiscalYear';
import ContractsTable from './partials/ContractsTable.vue';
import EmptyContractsState from './partials/EmptyContractsState.vue';
import PageHeader from './partials/PageHeader.vue';

const props = defineProps<{
    contracts: App.Data.User.Contract.PaginatedContractListData;
    options: {
        vehicles: App.Data.User.Vehicle.VehicleOptionData[];
        companies: App.Data.User.Company.CompanyOptionData[];
        drivers: App.Data.User.Driver.DriverOptionData[];
    };
    query: App.Data.User.Contract.ContractIndexQueryData;
    /**
     * `true` ssi au moins un contrat existe en base. Source de vérité
     * unique pour décider du placeholder. Évite le flash lors du reset
     * de filtre — cf. note backend sur le bug placeholder.
     */
    hasAnyContract: boolean;
}>();

const { currentYear: fiscalYear } = useFiscalYear();
const filtersOpen = ref<boolean>(false);

const tableState = useContractsTable({
    query: props.query,
    vehicleOptions: props.options.vehicles,
    companyOptions: props.options.companies,
    driverOptions: props.options.drivers,
});

// Computed wrappers v-model fiables (cf. fix router.get).
const searchModel = computed<string>({
    get: () => tableState.state.search.value,
    set: (value: string) => {
        tableState.state.search.value = value;
    },
});

const vehicleSelectOptions = computed(() =>
    props.options.vehicles.map((v) => ({ value: v.id, label: v.label })),
);

const companySelectOptions = computed(() =>
    props.options.companies.map((c) => ({
        value: c.id,
        label: `${c.shortCode} · ${c.legalName}`,
    })),
);

const driverSelectOptions = computed(() =>
    props.options.drivers.map((d) => ({ value: d.id, label: d.fullName })),
);

const typeOptions = [
    { value: 'lcd', label: 'LCD (≤ 30 jours)' },
    { value: 'lld', label: 'LLD (> 30 jours)' },
];

const vehicleIdModel = computed<number | null>({
    get: () => tableState.state.filters.value.vehicleId,
    set: (value: string | number | null) => {
        tableState.state.setFilter(
            'vehicleId',
            typeof value === 'number' ? value : null,
        );
    },
});

const companyIdModel = computed<number | null>({
    get: () => tableState.state.filters.value.companyId,
    set: (value: string | number | null) => {
        tableState.state.setFilter(
            'companyId',
            typeof value === 'number' ? value : null,
        );
    },
});

const driverIdModel = computed<number | null>({
    get: () => tableState.state.filters.value.driverId,
    set: (value: string | number | null) => {
        tableState.state.setFilter(
            'driverId',
            typeof value === 'number' ? value : null,
        );
    },
});

const typeModel = computed<string | number>({
    get: () => tableState.state.filters.value.type ?? '',
    set: (value: string | number) => {
        const v = String(value);
        tableState.state.setFilter(
            'type',
            v === 'lcd' || v === 'lld' ? v : null,
        );
    },
});

const periodRange = computed({
    get: () => ({
        startDate: tableState.state.filters.value.periodStart,
        endDate: tableState.state.filters.value.periodEnd,
    }),
    set: (range: { startDate: string | null; endDate: string | null }) => {
        // patchFilters : update atomique en 1 seul reload. Évite la race
        // où la 1ère request `?periodStart=…` (sans periodEnd) revient
        // après la 2ème et écrase l'état avec un filtre incohérent
        // (cf. bug filtre période 2026-05).
        tableState.state.patchFilters({
            periodStart: range.startDate,
            periodEnd: range.endDate,
        });
    },
});
const periodOngoing = ref<boolean>(false);
</script>

<template>
    <Head title="Contrats" />

    <UserLayout>
        <div class="flex flex-col gap-6">
            <PageHeader />

            <EmptyContractsState v-if="!props.hasAnyContract" />

            <template v-else>
                <div class="flex flex-wrap items-center gap-3">
                    <div class="grow max-w-md">
                        <SearchInput
                            v-model="searchModel"
                            placeholder="Rechercher (immat, marque, modèle, entreprise, conducteur)"
                            aria-label="Rechercher un contrat"
                        />
                    </div>
                    <FilterPopover
                        v-model:open="filtersOpen"
                        :active-count="tableState.activeFiltersCount.value"
                        @reset="tableState.state.clearFilters"
                    >
                        <div class="flex flex-col gap-3">
                            <div>
                                <FieldLabel for="filter-vehicle">Véhicule</FieldLabel>
                                <SearchableSelect
                                    id="filter-vehicle"
                                    v-model="vehicleIdModel"
                                    placeholder="Tous les véhicules"
                                    :options="vehicleSelectOptions"
                                />
                            </div>
                            <div>
                                <FieldLabel for="filter-company">Entreprise</FieldLabel>
                                <SearchableSelect
                                    id="filter-company"
                                    v-model="companyIdModel"
                                    placeholder="Toutes les entreprises"
                                    :options="companySelectOptions"
                                />
                            </div>
                            <div>
                                <FieldLabel for="filter-driver">Conducteur</FieldLabel>
                                <SearchableSelect
                                    id="filter-driver"
                                    v-model="driverIdModel"
                                    placeholder="Tous les conducteurs"
                                    :options="driverSelectOptions"
                                />
                            </div>
                            <div>
                                <FieldLabel for="filter-type">Type</FieldLabel>
                                <SelectInput
                                    id="filter-type"
                                    v-model="typeModel"
                                    placeholder="Tous les types"
                                    :options="typeOptions"
                                    nullable
                                />
                            </div>
                            <div>
                                <FieldLabel for="filter-period">Période active</FieldLabel>
                                <DateRangePicker
                                    id="filter-period"
                                    v-model:range="periodRange"
                                    v-model:ongoing="periodOngoing"
                                    :year="fiscalYear"
                                />
                            </div>
                        </div>
                    </FilterPopover>
                </div>

                <ContractsTable
                    :contracts="contracts.data"
                    :columns="tableState.columns"
                    :active-sort-column-key="tableState.activeSortColumnKey.value"
                    :sort-direction="tableState.state.sort.value.direction"
                    :badge-tone="tableState.badgeTone"
                    :short-label="tableState.shortLabel"
                    @header-click="tableState.onHeaderClick"
                    @row-click="tableState.onRowClick"
                />

                <Paginator
                    :meta="contracts.meta"
                    @page-change="tableState.state.setPage"
                    @per-page-change="tableState.state.setPerPage"
                />
            </template>
        </div>
    </UserLayout>
</template>
