<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import FieldLabel from '@/Components/Ui/FieldLabel/FieldLabel.vue';
import InlineYearSelector from '@/Components/Ui/InlineYearSelector/InlineYearSelector.vue';
import Paginator from '@/Components/Ui/Paginator/Paginator.vue';
import SearchInput from '@/Components/Ui/SearchInput/SearchInput.vue';
import SelectInput from '@/Components/Ui/SelectInput/SelectInput.vue';
import FilterPopover from '@/Components/Ui/Table/FilterPopover.vue';
import TextInput from '@/Components/Ui/TextInput/TextInput.vue';
import { useCompaniesTable } from '@/Composables/Company/Index/useCompaniesTable';
import CompaniesTable from './partials/CompaniesTable.vue';
import EmptyCompaniesState from './partials/EmptyCompaniesState.vue';
import PageHeader from './partials/PageHeader.vue';

const props = defineProps<{
    companies: App.Data.User.Company.PaginatedCompanyListData;
    query: App.Data.User.Company.CompanyIndexQueryData;
    /**
     * `true` ssi au moins une entreprise existe en base. Source de vérité
     * unique pour décider du placeholder. Évite le flash lors du reset de
     * filtre · cf. note backend sur le bug placeholder.
     */
    hasAnyCompany: boolean;
    selectedYear: number;
    /**
     * Scope d'années dynamique calculé depuis les contrats actifs
     * (chantier η Phase 3). Remplace l'ancienne config statique
     * `floty.fiscal.available_years` qui était lue via `useFiscalYear`.
     */
    yearScope: App.Data.Shared.YearScopeData;
    /**
     * P0.1 (audit perf 2026-05-16) · Inertia::defer · arrive en 2e
     * round-trip apres mount initial. Map indexee par companyId.
     * Non typee par TS Transformer (Spatie Data ne reconnait pas les
     * array indexees) · type manuel ici.
     */
    costs?: Record<
        number,
        { annualTaxDue: number; rentalPriceTotal: number | null }
    >;
}>();

const availableYears = computed<readonly number[]>(() => props.yearScope.availableYears);
const filtersOpen = ref<boolean>(false);

const tableState = useCompaniesTable({
    query: props.query,
    selectedYear: props.selectedYear,
});

// P0.1 (audit perf 2026-05-16) · Ref local miroir de la prop `costs` ·
// reset à `undefined` immédiatement à chaque changement de page/année
// AVANT le reload pour forcer les skeletons sur les 2 cellules. Sans
// ce miroir, Inertia préserve la prop deferred lors des partial reloads
// (visit year-change déclenché par useServerTableState ne re-touche pas
// `costs`) · les valeurs périmées resteraient affichées ~200-500 ms le
// temps de la RTT du reload manuel, sans skeleton visible. Pattern
// identique à Planning et Flotte (cf. mémoire
// `feedback_inertia_defer_with_partial_reload`).
const localCosts = ref<typeof props.costs>(props.costs);

// Sync depuis la prop · capte l'auto-fetch initial du defer ET le
// retour du reload manuel après year/filter change.
watch(
    () => props.costs,
    (next) => {
        localCosts.value = next;
    },
);

// Reset + re-fetch sur changement de page de la table (filtre, tri,
// pagination) **OU d'année** · les coûts dépendent de l'année
// sélectionnée (`annualTaxDue` change selon les barèmes annuels). Le
// `router.get` interne de `useServerTableState` ne demande que
// `['companies', 'query', 'selectedYear']` · sans ce watcher, les
// cellules resteraient gelées au coût de l'année initiale après
// year-change (les entreprises ne changent pas → IDs identiques).
// Clé composite `{year}|{ids}` · re-fetch si l'un OU l'autre change.
// Vue 3 `watch` sans `immediate: true` ne fire pas au mount.
watch(
    () => `${props.selectedYear}|${props.companies.data.map((c) => c.id).join(',')}`,
    () => {
        localCosts.value = undefined;
        router.reload({ only: ['costs'] });
    },
);

const yearOptions = computed<{ value: number; label: string }[]>(() =>
    availableYears.value.map((year) => ({ value: year, label: String(year) })),
);

const yearModel = computed<number>({
    get: () => tableState.state.filters.value.year,
    set: (v) => tableState.state.setFilter('year', v),
});

const searchModel = computed<string>({
    get: () => tableState.state.search.value,
    set: (value: string) => {
        tableState.state.search.value = value;
    },
});

const isActiveOptions = [
    { value: '', label: 'Toutes' },
    { value: 'yes', label: 'Active' },
    { value: 'no', label: 'Inactive' },
];

const isActiveModel = computed<string | number>({
    get: () => {
        if (tableState.state.filters.value.isActive === null) {
            return '';
        }

        return tableState.state.filters.value.isActive ? 'yes' : 'no';
    },
    set: (value: string | number) => {
        const v = String(value);
        const next = v === 'yes' ? true : v === 'no' ? false : null;
        tableState.state.setFilter('isActive', next);
    },
});

const contractsScopeOptions = [
    { value: '', label: 'Toutes' },
    { value: 'with', label: 'Avec locations' },
    { value: 'without', label: 'Sans locations' },
];

const contractsScopeModel = computed<string | number>({
    get: () => tableState.state.filters.value.contractsScope ?? '',
    set: (value: string | number) => {
        const v = String(value);
        tableState.state.setFilter(
            'contractsScope',
            v === 'with' || v === 'without' ? v : null,
        );
    },
});

const cityModel = computed<string>({
    get: () => tableState.state.filters.value.city ?? '',
    set: (value: string) => {
        tableState.state.setFilter('city', value === '' ? null : value);
    },
});

const activeFiltersCount = computed<number>(() => {
    let n = 0;
    const f = tableState.state.filters.value;

    if (f.isActive !== null) {
        n += 1;
    }

    if (f.contractsScope !== null) {
        n += 1;
    }

    if (f.city !== null && f.city !== '') {
        n += 1;
    }

    return n;
});
</script>

<template>
    <Head title="Entreprises" />

    <UserLayout>
        <div class="flex flex-col gap-6">
            <PageHeader :fiscal-year="props.selectedYear" />

            <div v-if="!props.hasAnyCompany">
                <EmptyCompaniesState />
            </div>

            <template v-else>
                <div class="flex flex-wrap items-center gap-3">
                    <div class="grow max-w-md">
                        <SearchInput
                            v-model="searchModel"
                            placeholder="Rechercher (nom, SIREN, code court)"
                            aria-label="Rechercher une entreprise"
                        />
                    </div>
                    <FilterPopover
                        v-model:open="filtersOpen"
                        :active-count="activeFiltersCount"
                        @reset="tableState.state.clearFilters"
                    >
                        <div class="flex flex-col gap-3">
                            <div>
                                <FieldLabel for="filter-active"
                                    >Activité</FieldLabel
                                >
                                <SelectInput
                                    id="filter-active"
                                    v-model="isActiveModel"
                                    :options="isActiveOptions"
                                />
                            </div>
                            <div>
                                <FieldLabel for="filter-contracts"
                                    >Locations</FieldLabel
                                >
                                <SelectInput
                                    id="filter-contracts"
                                    v-model="contractsScopeModel"
                                    :options="contractsScopeOptions"
                                />
                            </div>
                            <div>
                                <FieldLabel for="filter-city">Ville</FieldLabel>
                                <TextInput
                                    id="filter-city"
                                    v-model="cityModel"
                                    placeholder="Lyon, Paris…"
                                />
                            </div>
                        </div>
                    </FilterPopover>
                    <div class="ml-auto">
                        <InlineYearSelector
                            id="companies-year"
                            v-model="yearModel"
                            label="Colonnes financières"
                            :options="yearOptions"
                            :disabled="yearOptions.length <= 1"
                        />
                    </div>
                </div>

                <CompaniesTable
                    :companies="companies.data"
                    :costs="localCosts"
                    :columns="tableState.columns.value"
                    :active-sort-column-key="
                        tableState.activeSortColumnKey.value
                    "
                    :sort-direction="tableState.state.sort.value.direction"
                    @header-click="tableState.onHeaderClick"
                    @row-click="tableState.onRowClick"
                />

                <Paginator
                    :meta="companies.meta"
                    @page-change="tableState.state.setPage"
                    @per-page-change="tableState.state.setPerPage"
                />
            </template>
        </div>
    </UserLayout>
</template>
