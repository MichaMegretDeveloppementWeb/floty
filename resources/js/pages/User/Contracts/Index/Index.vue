<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref, toRef } from 'vue';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import DateRangePicker from '@/Components/Ui/DateRangePicker/DateRangePicker.vue';
import FieldLabel from '@/Components/Ui/FieldLabel/FieldLabel.vue';
import SearchableSelect from '@/Components/Ui/SearchableSelect/SearchableSelect.vue';
import SelectInput from '@/Components/Ui/SelectInput/SelectInput.vue';
import FilterPopover from '@/Components/Ui/Table/FilterPopover.vue';
import { useContractsTable } from '@/Composables/Contract/Index/useContractsTable';
import { useFiscalYear } from '@/Composables/Shared/useFiscalYear';
import ContractsTable from './partials/ContractsTable.vue';
import EmptyContractsState from './partials/EmptyContractsState.vue';
import PageHeader from './partials/PageHeader.vue';

const props = defineProps<{
    contracts: App.Data.User.Contract.ContractListItemData[];
    options: {
        vehicles: App.Data.User.Vehicle.VehicleOptionData[];
        companies: App.Data.User.Company.CompanyOptionData[];
        drivers: App.Data.User.Driver.DriverOptionData[];
    };
}>();

const { currentYear: fiscalYear } = useFiscalYear();
const filtersOpen = ref<boolean>(false);

const contractsRef = toRef(props, 'contracts');
const vehicleOptionsRef = computed(() => props.options.vehicles);
const companyOptionsRef = computed(() => props.options.companies);
const driverOptionsRef = computed(() => props.options.drivers);

const tableState = useContractsTable({
    contracts: contractsRef,
    vehicleOptions: vehicleOptionsRef,
    companyOptions: companyOptionsRef,
    driverOptions: driverOptionsRef,
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

const periodRange = computed({
    get: () => ({
        startDate: tableState.state.filters.value.periodStart,
        endDate: tableState.state.filters.value.periodEnd,
    }),
    set: (range: { startDate: string | null; endDate: string | null }) => {
        tableState.state.setFilter('periodStart', range.startDate);
        tableState.state.setFilter('periodEnd', range.endDate);
    },
});
const periodOngoing = ref<boolean>(false);

function vehicleIdModelGet(): number | null {
    return tableState.state.filters.value.vehicleId;
}
function vehicleIdModelSet(value: string | number | null): void {
    tableState.state.setFilter(
        'vehicleId',
        typeof value === 'number' ? value : null,
    );
}
function companyIdModelGet(): number | null {
    return tableState.state.filters.value.companyId;
}
function companyIdModelSet(value: string | number | null): void {
    tableState.state.setFilter(
        'companyId',
        typeof value === 'number' ? value : null,
    );
}
function driverIdModelGet(): number | null {
    return tableState.state.filters.value.driverId;
}
function driverIdModelSet(value: string | number | null): void {
    tableState.state.setFilter(
        'driverId',
        typeof value === 'number' ? value : null,
    );
}

const vehicleIdModel = computed({
    get: vehicleIdModelGet,
    set: vehicleIdModelSet,
});
const companyIdModel = computed({
    get: companyIdModelGet,
    set: companyIdModelSet,
});
const driverIdModel = computed({
    get: driverIdModelGet,
    set: driverIdModelSet,
});

const typeModel = computed({
    get: () => tableState.state.filters.value.type ?? '',
    set: (value: string | number) => {
        const v = String(value);
        tableState.state.setFilter(
            'type',
            v === 'lcd' || v === 'lld' ? v : null,
        );
    },
});
</script>

<template>
    <Head title="Contrats" />

    <UserLayout>
        <div class="flex flex-col gap-6">
            <PageHeader />

            <EmptyContractsState v-if="props.contracts.length === 0" />
            <template v-else>
                <div class="flex justify-start">
                    <FilterPopover
                        v-model:open="filtersOpen"
                        :active-count="
                            tableState.state.activeFiltersCount.value
                        "
                        @reset="tableState.state.clearFilters"
                    >
                        <div class="flex flex-col gap-3">
                            <div>
                                <FieldLabel for="filter-vehicle"
                                    >Véhicule</FieldLabel
                                >
                                <SearchableSelect
                                    id="filter-vehicle"
                                    v-model="vehicleIdModel"
                                    placeholder="Tous les véhicules"
                                    :options="vehicleSelectOptions"
                                />
                            </div>
                            <div>
                                <FieldLabel for="filter-company"
                                    >Entreprise</FieldLabel
                                >
                                <SearchableSelect
                                    id="filter-company"
                                    v-model="companyIdModel"
                                    placeholder="Toutes les entreprises"
                                    :options="companySelectOptions"
                                />
                            </div>
                            <div>
                                <FieldLabel for="filter-driver"
                                    >Conducteur</FieldLabel
                                >
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
                                />
                            </div>
                            <div>
                                <FieldLabel for="filter-period"
                                    >Période active</FieldLabel
                                >
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
                    :contracts="tableState.rows.value"
                    :columns="tableState.columns.value"
                    :sort-key="tableState.state.sort.value.key"
                    :sort-direction="tableState.state.sort.value.direction"
                    :badge-tone="tableState.badgeTone"
                    :short-label="tableState.shortLabel"
                    @sort="tableState.state.setSort"
                    @row-click="tableState.handleRowClick"
                />
            </template>
        </div>
    </UserLayout>
</template>
