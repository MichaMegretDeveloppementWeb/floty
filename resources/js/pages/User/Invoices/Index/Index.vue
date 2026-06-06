<script setup lang="ts">
/**
 * Invoices index page. Server-side paginated table (ADR-0020) with
 * companyId / year / month filters and number/company search.
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
        /** Year bounds covered by issued invoices; null if none. */
        yearBounds: { min: number; max: number } | null;
    };
}>();

const filtersOpen = ref<boolean>(false);

const companyOptions = computed(() => props.options.companies);

const tableState = useInvoicesTable({
    query: props.query,
    companyOptions: companyOptions.value,
});

const {
    searchModel,
    companyIdModel,
    yearModel,
    monthModel,
    divergentOnlyModel,
    includeObsoleteModel,
} = tableState;

const companySelectOptions = computed(() =>
    companyOptions.value.map((c) => ({
        value: c.id,
        label: `${c.shortCode} · ${c.legalName}`,
    })),
);

// Year filter: descending list spanning from the oldest invoice year
// up to max(current year, latest invoice year). Falls back to the
// current year alone if no invoices exist.
const yearOptions = computed<{ value: number | null; label: string }[]>(() => {
    const currentYear = new Date().getFullYear();
    const bounds = props.options.yearBounds;

    const min = bounds?.min ?? currentYear;
    const max = Math.max(bounds?.max ?? currentYear, currentYear);

    const years: number[] = [];

    for (let y = max; y >= min; y--) {
        years.push(y);
    }

    return [
        { value: null, label: 'Toutes les années' },
        ...years.map((y) => ({ value: y, label: String(y) })),
    ];
});

const monthOptions: { value: number | null; label: string }[] = [
    { value: null, label: 'Tous les mois' },
    { value: 1, label: 'Janvier' }, { value: 2, label: 'Février' },
    { value: 3, label: 'Mars' }, { value: 4, label: 'Avril' },
    { value: 5, label: 'Mai' }, { value: 6, label: 'Juin' },
    { value: 7, label: 'Juillet' }, { value: 8, label: 'Août' },
    { value: 9, label: 'Septembre' }, { value: 10, label: 'Octobre' },
    { value: 11, label: 'Novembre' }, { value: 12, label: 'Décembre' },
];
</script>

<template>
    <Head title="Annexes de facture" />

    <UserLayout>
        <div class="flex flex-col gap-6">
            <header class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="eyebrow mb-1">Facturation</p>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 md:text-3xl">
                        Annexes de facture
                    </h1>
                    <p class="mt-1 text-base text-slate-600">
                        Historique des annexes mensuelles émises. Document
                        figé à l'émission, non modifiable.
                    </p>
                </div>
            </header>

            <div
                v-if="!props.hasAnyInvoice"
                class="rounded-2xl border border-slate-200 bg-white p-12 text-center"
            >
                <p class="text-sm font-medium text-slate-700">
                    Aucune annexe émise pour l'instant.
                </p>
                <p class="mt-1 text-xs text-slate-500">
                    Générez une annexe de facture mensuelle depuis la fiche d'une entreprise.
                </p>
            </div>

            <template v-else>
                <div class="flex flex-wrap items-center gap-3">
                    <div class="grow max-w-md">
                        <SearchInput
                            v-model="searchModel"
                            placeholder="Rechercher (numéro, entreprise)"
                            aria-label="Rechercher une annexe"
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
                                    dropdown-in-flow
                                    v-model="companyIdModel"
                                    placeholder="Toutes les entreprises"
                                    :options="companySelectOptions"
                                />
                            </div>
                            <div>
                                <FieldLabel for="filter-invoice-year">Année</FieldLabel>
                                <SelectInput
                                    id="filter-invoice-year"
                                    v-model="yearModel"
                                    :options="yearOptions"
                                />
                            </div>
                            <div>
                                <FieldLabel for="filter-invoice-month">Mois</FieldLabel>
                                <SelectInput
                                    id="filter-invoice-month"
                                    v-model="monthModel"
                                    :options="monthOptions"
                                />
                            </div>
                            <div class="border-t border-slate-100 pt-3 flex flex-col gap-3">
                                <CheckboxInput
                                    id="filter-invoice-divergent"
                                    v-model="divergentOnlyModel"
                                    label="À régénérer uniquement"
                                    hint="Périmètre contractuel modifié depuis l'émission"
                                />
                                <CheckboxInput
                                    id="filter-invoice-obsolete"
                                    v-model="includeObsoleteModel"
                                    label="Inclure les versions obsolètes"
                                    hint="Anciennes annexes remplacées par régénération"
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
