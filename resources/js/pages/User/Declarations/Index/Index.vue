<script setup lang="ts">
/**
 * Server-side paginated fiscal declarations index. `hasAnyDeclaration`
 * separates the intrinsically empty case from the filtered-out case.
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
import { useDeclarationsIndex } from '@/Composables/Declaration/useDeclarationsIndex';
import DeclarationsTable from './partials/DeclarationsTable.vue';
import EmptyState from './partials/EmptyState.vue';

const props = defineProps<{
    declarations: App.Data.User.FiscalDeclaration.PaginatedDeclarationListData;
    query: App.Data.User.FiscalDeclaration.DeclarationIndexQueryData;
    options: {
        companies: Array<{ id: number; shortCode: string; legalName: string }>;
        yearBounds: { min: number; max: number } | null;
    };
    hasAnyDeclaration: boolean;
}>();

const filtersOpen = ref<boolean>(false);

const tableState = useDeclarationsIndex({
    query: props.query,
    companyOptions: props.options.companies,
});

const {
    companyIdModel,
    fiscalYearModel,
    statusModel,
    obsoleteOnlyModel,
    searchModel,
} = tableState;

const companySelectOptions = computed(() =>
    props.options.companies.map((c) => ({
        value: c.id,
        label: `${c.shortCode} · ${c.legalName}`,
    })),
);

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

const statusOptions: { value: string | null; label: string }[] = [
    { value: null, label: 'Tous les statuts' },
    { value: 'draft', label: 'Brouillon' },
    { value: 'deferred', label: 'Reportée' },
    { value: 'generated', label: 'Générée' },
];
</script>

<template>
    <Head title="Déclarations fiscales · Floty" />

    <UserLayout>
        <div class="flex flex-col gap-6">
            <header class="flex flex-col gap-1">
                <h1 class="text-2xl font-semibold text-slate-900">
                    Déclarations fiscales
                </h1>
                <p class="text-sm text-slate-500">
                    Historique des déclarations annuelles. Document figé à
                    la génération, régénération possible si le périmètre
                    contractuel évolue.
                </p>
            </header>

            <EmptyState v-if="!props.hasAnyDeclaration" />

            <template v-else>
                <div class="flex flex-wrap items-center gap-3">
                    <div class="max-w-md grow">
                        <SearchInput
                            v-model="searchModel"
                            placeholder="Rechercher par référence (ex. DECL-ACM-2024)"
                            aria-label="Rechercher par référence de déclaration"
                        />
                    </div>
                    <FilterPopover
                        v-model:open="filtersOpen"
                        :active-count="tableState.activeFiltersCount.value"
                        @reset="tableState.state.clearFilters"
                    >
                        <div class="flex flex-col gap-3">
                            <div v-if="companySelectOptions.length > 0">
                                <FieldLabel for="filter-decl-company">Entreprise</FieldLabel>
                                <SearchableSelect
                                    id="filter-decl-company"
                                    v-model="companyIdModel"
                                    placeholder="Toutes les entreprises"
                                    :options="companySelectOptions"
                                />
                            </div>
                            <div>
                                <FieldLabel for="filter-decl-year">Année fiscale</FieldLabel>
                                <SelectInput
                                    id="filter-decl-year"
                                    v-model="fiscalYearModel"
                                    :options="yearOptions"
                                />
                            </div>
                            <div>
                                <FieldLabel for="filter-decl-status">Statut</FieldLabel>
                                <SelectInput
                                    id="filter-decl-status"
                                    v-model="statusModel"
                                    :options="statusOptions"
                                />
                            </div>
                            <div class="border-t border-slate-100 pt-3">
                                <CheckboxInput
                                    id="filter-decl-obsolete"
                                    v-model="obsoleteOnlyModel"
                                    label="Obsolètes uniquement"
                                    hint="Périmètre contractuel modifié depuis la génération"
                                />
                            </div>
                        </div>
                    </FilterPopover>
                </div>

                <DeclarationsTable
                    :declarations="declarations.data"
                    :columns="tableState.columns"
                    :active-sort-column-key="tableState.activeSortColumnKey.value"
                    :sort-direction="tableState.state.sort.value.direction"
                    @header-click="tableState.onHeaderClick"
                    @row-click="tableState.onRowClick"
                />

                <Paginator
                    :meta="declarations.meta"
                    @page-change="tableState.state.setPage"
                    @per-page-change="tableState.state.setPerPage"
                />
            </template>
        </div>
    </UserLayout>
</template>
