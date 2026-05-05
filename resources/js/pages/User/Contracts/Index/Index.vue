<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { CalendarDays } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import Button from '@/Components/Ui/Button/Button.vue';
import DateRangePicker from '@/Components/Ui/DateRangePicker/DateRangePicker.vue';
import FieldLabel from '@/Components/Ui/FieldLabel/FieldLabel.vue';
import Paginator from '@/Components/Ui/Paginator/Paginator.vue';
import SearchableSelect from '@/Components/Ui/SearchableSelect/SearchableSelect.vue';
import SearchInput from '@/Components/Ui/SearchInput/SearchInput.vue';
import SelectInput from '@/Components/Ui/SelectInput/SelectInput.vue';
import FilterPopover from '@/Components/Ui/Table/FilterPopover.vue';
import { useContractsTable } from '@/Composables/Contract/Index/useContractsTable';
import { useFiscalYear } from '@/Composables/Shared/useFiscalYear';
import { formatDateFr } from '@/Utils/format/formatDateFr';
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

const { availableYears } = useFiscalYear();
const filtersOpen = ref<boolean>(false);

const tableState = useContractsTable({
    query: props.query,
    vehicleOptions: props.options.vehicles,
    companyOptions: props.options.companies,
    driverOptions: props.options.drivers,
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

// ---------------------------------------------------------------
// Sélecteur scope hybride année/période (chantier J)
// ---------------------------------------------------------------
//
// 2 modes mutuellement exclusifs côté front :
//  - mode 'year'   : SelectInput compact, envoie ?year=YYYY
//  - mode 'period' : DateRangePicker dans popover, envoie ?periodStart=&periodEnd=
//
// Le mode initial est dérivé des params URL : `year` présent → mode year ;
// `periodStart/End` présents → mode period ; sinon défaut année courante.

type ScopeMode = 'year' | 'period';

const initialMode: ScopeMode
    = props.query.year !== null
        ? 'year'
        : props.query.periodStart !== null || props.query.periodEnd !== null
            ? 'period'
            : 'year';

const scopeMode = ref<ScopeMode>(initialMode);

const yearOptions = computed<{ value: number; label: string }[]>(() =>
    availableYears.value.map((year) => ({ value: year, label: String(year) })),
);

// Année par défaut : valeur du DTO query si mode year, sinon dernière
// année disponible. L'utilisateur peut la changer via le SelectInput.
const defaultYear = computed<number>(() => {
    if (props.query.year !== null) {
        return props.query.year;
    }

    const max = availableYears.value.length === 0
        ? new Date().getFullYear()
        : Math.max(...availableYears.value);

    return max;
});

const yearModel = computed<number>({
    get: () => tableState.state.filters.value.year ?? defaultYear.value,
    set: (v: number) => {
        // En mode year : on set year, on efface periodStart/End. patchFilters
        // pour update atomique en 1 seul reload.
        tableState.state.patchFilters({
            year: v,
            periodStart: null,
            periodEnd: null,
        });
    },
});

const periodRange = computed({
    get: () => ({
        startDate: tableState.state.filters.value.periodStart,
        endDate: tableState.state.filters.value.periodEnd,
    }),
    set: (range: { startDate: string | null; endDate: string | null }) => {
        // En mode period : on set periodStart/End, on efface year.
        tableState.state.patchFilters({
            year: null,
            periodStart: range.startDate,
            periodEnd: range.endDate,
        });
    },
});
const periodOngoing = ref<boolean>(false);

function setScopeMode(mode: ScopeMode): void {
    scopeMode.value = mode;

    if (mode === 'year') {
        // Bascule en année → applique l'année par défaut, efface period
        if (tableState.state.filters.value.year === null) {
            tableState.state.patchFilters({
                year: defaultYear.value,
                periodStart: null,
                periodEnd: null,
            });
        }
    } else {
        // Bascule en période → garde period si déjà saisi, sinon clear year
        if (
            tableState.state.filters.value.periodStart === null
            && tableState.state.filters.value.periodEnd === null
        ) {
            tableState.state.patchFilters({
                year: null,
                periodStart: null,
                periodEnd: null,
            });
        } else {
            tableState.state.setFilter('year', null);
        }
    }
}

// Popover période personnalisée
const periodPopoverOpen = ref<boolean>(false);
const popoverRoot = ref<HTMLElement | null>(null);

function handleDocumentMouseDown(event: MouseEvent): void {
    if (!periodPopoverOpen.value) {
return;
}

    const target = event.target as Node | null;

    if (target === null) {
return;
}

    if (popoverRoot.value !== null && popoverRoot.value.contains(target)) {
return;
}

    periodPopoverOpen.value = false;
}

function handleEscape(event: KeyboardEvent): void {
    if (event.key === 'Escape' && periodPopoverOpen.value) {
        periodPopoverOpen.value = false;
    }
}

onMounted(() => {
    document.addEventListener('mousedown', handleDocumentMouseDown);
    document.addEventListener('keydown', handleEscape);
});

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', handleDocumentMouseDown);
    document.removeEventListener('keydown', handleEscape);
});

const pickerYear = computed<number>(() => {
    const start = tableState.state.filters.value.periodStart;

    if (start !== null) {
        return Number.parseInt(start.slice(0, 4), 10);
    }

    return defaultYear.value;
});

const periodLabel = computed<string>(() => {
    const start = tableState.state.filters.value.periodStart;
    const end = tableState.state.filters.value.periodEnd;

    if (start === null && end === null) {
return 'Aucune période sélectionnée';
}

    const s = start === null ? '…' : formatDateFr(start);
    const e = end === null ? '…' : formatDateFr(end);

    return `${s} → ${e}`;
});
</script>

<template>
    <Head title="Contrats" />

    <UserLayout>
        <div class="flex flex-col gap-6">
            <PageHeader />

            <EmptyContractsState v-if="!props.hasAnyContract" />

            <template v-else>
                <!-- Sélecteur scope hybride année/période (hors panneau filtres) -->
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div class="flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white p-1">
                        <button
                            type="button"
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

                    <!-- Mode année : SelectInput compact -->
                    <div v-if="scopeMode === 'year'" class="flex flex-col gap-1">
                        <FieldLabel for="contracts-year">Exercice</FieldLabel>
                        <SelectInput
                            id="contracts-year"
                            v-model.number="yearModel"
                            :options="yearOptions"
                            :disabled="yearOptions.length <= 1"
                        />
                    </div>

                    <!-- Mode période : bouton + popover DateRangePicker -->
                    <div v-else ref="popoverRoot" class="relative">
                        <Button
                            variant="ghost"
                            size="sm"
                            @click="periodPopoverOpen = !periodPopoverOpen"
                        >
                            <template #icon-left>
                                <CalendarDays
                                    :size="14"
                                    :stroke-width="1.75"
                                />
                            </template>
                            {{ periodLabel }}
                        </Button>

                        <div
                            v-if="periodPopoverOpen"
                            class="fixed inset-0 z-40 bg-slate-900/20 sm:hidden"
                            aria-hidden="true"
                            @click="periodPopoverOpen = false"
                        />
                        <div
                            v-if="periodPopoverOpen"
                            class="fixed inset-x-4 bottom-4 z-50 flex max-h-[80vh] flex-col rounded-lg border border-slate-200 bg-white shadow-2xl sm:absolute sm:inset-x-auto sm:bottom-auto sm:right-0 sm:top-full sm:mt-2 sm:max-h-[calc(100vh-8rem)] sm:w-[360px] sm:max-w-[calc(100vw-2rem)] sm:shadow-lg"
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
