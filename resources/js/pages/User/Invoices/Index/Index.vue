<script setup lang="ts">
/**
 * Page Index Factures (Phase 14.F V1.2). Liste paginée server-side
 * (cf. ADR-0020) avec filtres `?companyId`, `?vehicleId`, `?year`,
 * `?month` + search sur le numéro de facture / entreprise.
 */
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import CheckboxInput from '@/Components/Ui/CheckboxInput/CheckboxInput.vue';
import FieldLabel from '@/Components/Ui/FieldLabel/FieldLabel.vue';
import Paginator from '@/Components/Ui/Paginator/Paginator.vue';
import SearchableSelect from '@/Components/Ui/SearchableSelect/SearchableSelect.vue';
import SearchInput from '@/Components/Ui/SearchInput/SearchInput.vue';
import SelectInput from '@/Components/Ui/SelectInput/SelectInput.vue';
import FilterPopover from '@/Components/Ui/Table/FilterPopover.vue';
import { useInvoicesTable } from '@/Composables/Invoice/Index/useInvoicesTable';
import InvoicesTable from './partials/InvoicesTable.vue';

const props = defineProps<{
    invoices: App.Data.User.Invoice.PaginatedInvoiceListData;
    query: App.Data.User.Invoice.InvoiceIndexQueryData;
    hasAnyInvoice: boolean;
    options: {
        companies: App.Data.User.Company.CompanyOptionData[];
        vehicles: App.Data.User.Vehicle.VehicleOptionData[];
        /** Bornes des années couvertes par les factures émises ; null si aucune. */
        yearBounds: { min: number; max: number } | null;
    };
}>();

const filtersOpen = ref<boolean>(false);

const companyOptions = computed(() => props.options.companies);
const vehicleOptions = computed(() => props.options.vehicles);

const tableState = useInvoicesTable({
    query: props.query,
    companyOptions: companyOptions.value,
    vehicleOptions: vehicleOptions.value,
});

const searchModel = computed<string>({
    get: () => tableState.state.search.value,
    set: (value: string) => {
        tableState.state.search.value = value;
    },
});

const companySelectOptions = computed(() =>
    companyOptions.value.map((c) => ({
        value: c.id,
        label: `${c.shortCode} · ${c.legalName}`,
    })),
);

const vehicleSelectOptions = computed(() =>
    vehicleOptions.value.map((v) => ({ value: v.id, label: v.label })),
);

const companyIdModel = computed<number | null>({
    get: () => tableState.state.filters.value.companyId,
    set: (value: string | number | null) => {
        tableState.state.setFilter(
            'companyId',
            typeof value === 'number' ? value : null,
        );
    },
});

const vehicleIdModel = computed<number | null>({
    get: () => tableState.state.filters.value.vehicleId,
    set: (value: string | number | null) => {
        tableState.state.setFilter(
            'vehicleId',
            typeof value === 'number' ? value : null,
        );
    },
});

// Liste des années à proposer dans le filtre :
// `[min(plus ancienne facture)..max(année courante, max facture)]`,
// décroissante. Si pas de facture en base, on retombe sur l'année
// courante seule (cas table vide, mais l'UI cache l'ensemble du
// listing dans ce cas via `hasAnyInvoice`).
const yearOptions = computed(() => {
    const currentYear = new Date().getFullYear();
    const bounds = props.options.yearBounds;

    const min = bounds?.min ?? currentYear;
    const max = Math.max(bounds?.max ?? currentYear, currentYear);

    const years: number[] = [];

    for (let y = max; y >= min; y--) {
        years.push(y);
    }

    return years.map((y) => ({ value: y, label: String(y) }));
});

const yearModel = computed<number | null>({
    get: () => tableState.state.filters.value.year,
    set: (value: string | number | null) => {
        tableState.state.setFilter(
            'year',
            typeof value === 'number' ? value : null,
        );
    },
});

const monthOptions = [
    { value: 1, label: 'Janvier' }, { value: 2, label: 'Février' },
    { value: 3, label: 'Mars' }, { value: 4, label: 'Avril' },
    { value: 5, label: 'Mai' }, { value: 6, label: 'Juin' },
    { value: 7, label: 'Juillet' }, { value: 8, label: 'Août' },
    { value: 9, label: 'Septembre' }, { value: 10, label: 'Octobre' },
    { value: 11, label: 'Novembre' }, { value: 12, label: 'Décembre' },
];

const monthModel = computed<number | null>({
    get: () => tableState.state.filters.value.month,
    set: (value: string | number | null) => {
        tableState.state.setFilter(
            'month',
            typeof value === 'number' ? value : null,
        );
    },
});

const divergentOnlyModel = computed<boolean>({
    get: () => tableState.state.filters.value.divergentOnly,
    set: (value: boolean) => {
        tableState.state.setFilter('divergentOnly', value);
    },
});
</script>

<template>
    <Head title="Factures" />

    <UserLayout>
        <div class="flex flex-col gap-6">
            <header class="flex flex-col gap-1">
                <h1 class="text-2xl font-semibold text-slate-900">
                    Factures
                </h1>
                <p class="text-sm text-slate-500">
                    Historique des factures mensuelles émises. Document
                    figé à l'émission, non modifiable.
                </p>
            </header>

            <div
                v-if="!props.hasAnyInvoice"
                class="rounded-2xl border border-slate-200 bg-white p-12 text-center"
            >
                <p class="text-sm font-medium text-slate-700">
                    Aucune facture émise pour l'instant.
                </p>
                <p class="mt-1 text-xs text-slate-500">
                    Générez une facture mensuelle depuis la fiche d'une entreprise.
                </p>
            </div>

            <template v-else>
                <div class="flex flex-wrap items-center gap-3">
                    <div class="grow max-w-md">
                        <SearchInput
                            v-model="searchModel"
                            placeholder="Rechercher (numéro, entreprise)"
                            aria-label="Rechercher une facture"
                        />
                    </div>
                    <FilterPopover
                        v-model:open="filtersOpen"
                        :active-count="tableState.activeFiltersCount.value"
                        @reset="tableState.state.clearFilters"
                    >
                        <div class="flex flex-col gap-3">
                            <div v-if="companySelectOptions.length > 0">
                                <FieldLabel for="filter-invoice-company">Entreprise</FieldLabel>
                                <SearchableSelect
                                    id="filter-invoice-company"
                                    v-model="companyIdModel"
                                    placeholder="Toutes les entreprises"
                                    :options="companySelectOptions"
                                />
                            </div>
                            <div v-if="vehicleSelectOptions.length > 0">
                                <FieldLabel for="filter-invoice-vehicle">Véhicule</FieldLabel>
                                <SearchableSelect
                                    id="filter-invoice-vehicle"
                                    v-model="vehicleIdModel"
                                    placeholder="Tous les véhicules"
                                    :options="vehicleSelectOptions"
                                />
                            </div>
                            <div>
                                <FieldLabel for="filter-invoice-year">Année</FieldLabel>
                                <SelectInput
                                    id="filter-invoice-year"
                                    v-model.number="yearModel"
                                    placeholder="Toutes les années"
                                    :options="yearOptions"
                                    nullable
                                />
                            </div>
                            <div>
                                <FieldLabel for="filter-invoice-month">Mois</FieldLabel>
                                <SelectInput
                                    id="filter-invoice-month"
                                    v-model.number="monthModel"
                                    placeholder="Tous les mois"
                                    :options="monthOptions"
                                    nullable
                                />
                            </div>
                            <div class="border-t border-slate-100 pt-3">
                                <CheckboxInput
                                    id="filter-invoice-divergent"
                                    v-model="divergentOnlyModel"
                                    label="À régénérer uniquement"
                                    hint="Périmètre contractuel modifié depuis l'émission"
                                />
                            </div>
                        </div>
                    </FilterPopover>
                </div>

                <InvoicesTable
                    :invoices="invoices.data"
                    :columns="tableState.columns"
                    :active-sort-column-key="tableState.activeSortColumnKey.value"
                    :sort-direction="tableState.state.sort.value.direction"
                    @header-click="tableState.onHeaderClick"
                    @row-click="tableState.onRowClick"
                />

                <Paginator
                    :meta="invoices.meta"
                    @page-change="tableState.state.setPage"
                    @per-page-change="tableState.state.setPerPage"
                />
            </template>
        </div>
    </UserLayout>
</template>
