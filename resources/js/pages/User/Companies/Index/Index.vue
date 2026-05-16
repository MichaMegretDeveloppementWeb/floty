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

// P0.1 (audit perf 2026-05-16) · Re-fetch des `costs` à chaque
// changement de page de la table (filtre, tri, année, pagination).
// Le `router.get` interne de `useServerTableState` ne demande que
// `['companies', 'query', 'selectedYear']` · sans ce watcher, les
// cellules `annualTaxDue` / `rentalPriceTotal` resteraient en
// skeleton infini après le premier filtrage. `Inertia::defer`
// auto-trigger uniquement sur le 1er visit · les visits suivants
// doivent demander explicitement la prop deferred via un reload
// `only:`. Cf. mémoire `feedback_inertia_defer_with_partial_reload`.
//
// Vue 3 `watch` sans `immediate: true` ne fire pas au mount · pas
// besoin de skip-le-mount-initial. Le 1er fire correspond bien au
// 1er changement de filtre/tri/page/année.
watch(
    () => props.companies.data.map((c) => c.id).join(','),
    () => {
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
                    :costs="costs"
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
