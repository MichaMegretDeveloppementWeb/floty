<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { CalendarDays } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import DateRangePicker from '@/Components/Ui/DateRangePicker/DateRangePicker.vue';
import FieldLabel from '@/Components/Ui/FieldLabel/FieldLabel.vue';
import InlineYearSelector from '@/Components/Ui/InlineYearSelector/InlineYearSelector.vue';
import Paginator from '@/Components/Ui/Paginator/Paginator.vue';
import SearchableSelect from '@/Components/Ui/SearchableSelect/SearchableSelect.vue';
import SearchInput from '@/Components/Ui/SearchInput/SearchInput.vue';
import SelectInput from '@/Components/Ui/SelectInput/SelectInput.vue';
import FilterPopover from '@/Components/Ui/Table/FilterPopover.vue';
import { useContractsTable } from '@/Composables/Contract/Index/useContractsTable';
import { useContractsTimeScope } from '@/Composables/Contract/Index/useContractsTimeScope';
import ContractsTable from './partials/ContractsTable.vue';
import EmptyContractsState from './partials/EmptyContractsState.vue';
import PageHeader from './partials/PageHeader.vue';

/**
 * Per-contract costs map served via Inertia::defer.
 * Skeletons display in the totalTax / rentalPrice cells until the
 * second async request completes.
 */
type ContractCosts = Record<number, { totalTax: number; rentalPrice: number | null }>;

const props = defineProps<{
    contracts: App.Data.User.Contract.PaginatedContractListData;
    contractsCosts?: ContractCosts;
    options: {
        vehicles: App.Data.User.Vehicle.VehicleFilterOptionData[];
        companies: App.Data.User.Company.CompanyOptionData[];
        drivers: App.Data.User.Driver.DriverOptionData[];
    };
    query: App.Data.User.Contract.ContractIndexQueryData;
    hasAnyContract: boolean;
    yearScope: App.Data.Shared.YearScopeData;
}>();

const filtersOpen = ref<boolean>(false);

const tableState = useContractsTable({
    query: props.query,
    vehicleOptions: props.options.vehicles,
    companyOptions: props.options.companies,
    driverOptions: props.options.drivers,
});

// Local mirror of `contractsCosts` so skeletons reappear immediately on
// every filter/sort/page change. Inertia::defer auto-fetches only on
// the initial visit; subsequent partial reloads must be triggered
// manually via `router.reload({ only: ['contractsCosts'] })`.
const localContractsCosts = ref<ContractCosts | undefined>(props.contractsCosts);

watch(
    () => props.contractsCosts,
    (next) => {
        localContractsCosts.value = next;
    },
);

watch(
    () => props.contracts.data.map((c) => c.id).join(','),
    () => {
        localContractsCosts.value = undefined;
        router.reload({ only: ['contractsCosts'] });
    },
);

const availableYears = computed<readonly number[]>(() => props.yearScope.availableYears);
const {
    scopeMode,
    setScopeMode,
    yearOptions,
    yearModel,
    periodRange,
    periodOngoing,
    pickerYear,
    periodLabel,
    periodPopoverOpen,
    popoverRoot,
} = useContractsTimeScope({
    initialQuery: props.query,
    availableYears,
    tableState: tableState.state,
});

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
    { value: '', label: 'Tous les types' },
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
</script>

<template>
    <Head title="Locations" />

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
                            aria-label="Rechercher une location"
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
                                    dropdown-in-flow
                                    v-model="vehicleIdModel"
                                    placeholder="Tous les véhicules"
                                    :options="vehicleSelectOptions"
                                />
                            </div>
                            <div>
                                <FieldLabel for="filter-company">Entreprise</FieldLabel>
                                <SearchableSelect
                                    id="filter-company"
                                    dropdown-in-flow
                                    v-model="companyIdModel"
                                    placeholder="Toutes les entreprises"
                                    :options="companySelectOptions"
                                />
                            </div>
                            <div>
                                <FieldLabel for="filter-driver">Conducteur</FieldLabel>
                                <SearchableSelect
                                    id="filter-driver"
                                    dropdown-in-flow
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
                                    :options="typeOptions"
                                />
                            </div>
                        </div>
                    </FilterPopover>

                    <div class="ml-auto flex flex-wrap items-center gap-3">
                        <div
                            class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white p-1 shadow-sm"
                            role="tablist"
                            aria-label="Mode de filtre temporel"
                        >
                            <button
                                type="button"
                                role="tab"
                                :aria-selected="scopeMode === 'year'"
                                :class="[
                                    'rounded-md px-3 py-1 text-xs font-medium transition-colors duration-[120ms]',
                                    scopeMode === 'year'
                                        ? 'bg-blue-50 text-blue-700'
                                        : 'text-slate-600 hover:bg-slate-50',
                                ]"
                                @click="setScopeMode('year')"
                            >
                                Année
                            </button>
                            <button
                                type="button"
                                role="tab"
                                :aria-selected="scopeMode === 'period'"
                                :class="[
                                    'rounded-md px-3 py-1 text-xs font-medium transition-colors duration-[120ms]',
                                    scopeMode === 'period'
                                        ? 'bg-blue-50 text-blue-700'
                                        : 'text-slate-600 hover:bg-slate-50',
                                ]"
                                @click="setScopeMode('period')"
                            >
                                Période personnalisée
                            </button>
                        </div>

                        <InlineYearSelector
                            v-if="scopeMode === 'year'"
                            id="contracts-year"
                            v-model="yearModel"
                            :options="yearOptions"
                        />

                        <div v-else ref="popoverRoot" class="relative">
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white py-1.5 pr-3 pl-3 text-sm font-semibold text-slate-900 shadow-sm transition-colors duration-[120ms] ease-out hover:border-slate-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-200"
                                :aria-expanded="periodPopoverOpen"
                                @click="periodPopoverOpen = !periodPopoverOpen"
                            >
                                <CalendarDays
                                    :size="14"
                                    :stroke-width="1.75"
                                    class="shrink-0 text-slate-500"
                                    aria-hidden="true"
                                />
                                <span class="text-slate-700">Période :</span>
                                <span>{{ periodLabel }}</span>
                            </button>

                            <div
                                v-if="periodPopoverOpen"
                                class="fixed inset-0 z-40 bg-slate-900/20 sm:hidden"
                                aria-hidden="true"
                                @click="periodPopoverOpen = false"
                            />
                            <div
                                v-if="periodPopoverOpen"
                                class="fixed inset-x-4 bottom-4 z-50 flex max-h-[80vh] flex-col rounded-lg border border-slate-200 bg-white shadow-2xl sm:absolute sm:inset-x-auto sm:bottom-auto sm:left-auto sm:right-0 sm:top-full sm:mt-2 sm:max-h-[calc(100vh-8rem)] sm:w-[360px] sm:max-w-[calc(100vw-2rem)] sm:shadow-lg"
                            >
                                <div
                                    class="flex flex-col gap-3 overflow-y-auto p-4"
                                >
                                    <DateRangePicker
                                        id="contracts-period"
                                        v-model:range="periodRange"
                                        v-model:ongoing="periodOngoing"
                                        :year="pickerYear"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <ContractsTable
                    :contracts="contracts.data"
                    :costs="localContractsCosts"
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
