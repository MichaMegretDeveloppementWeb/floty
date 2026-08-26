<script setup lang="ts">
/**
 * Server-side paginated fiscal declarations index. `hasAnyDeclaration`
 * separates the intrinsically empty case from the filtered-out case.
 */
import { Head } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import CompanyYearPickerModal from '@/Components/Domain/Company/CompanyYearPickerModal.vue';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import Button from '@/Components/Ui/Button/Button.vue';
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
const pickerOpen = ref<boolean>(false);

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
    <Head title="Déclarations fiscales" />

    <UserLayout>
        <div class="flex flex-col gap-6">
            <header class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="eyebrow mb-1">Fiscalité</p>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 md:text-3xl">
                        Déclarations fiscales
                    </h1>
                    <p class="mt-1 text-base text-slate-600">
                        Historique des déclarations annuelles. Document figé
                        à la génération, régénération possible si le périmètre
                        contractuel évolue.
                    </p>
                </div>
                <Button @click="pickerOpen = true">
                    <template #icon-left>
                        <Plus :size="14" :stroke-width="1.75" />
                    </template>
                    Préparer une déclaration
                </Button>
            </header>

            <EmptyState v-if="!props.hasAnyDeclaration">
                <Button @click="pickerOpen = true">
                    <template #icon-left>
                        <Plus :size="14" :stroke-width="1.75" />
                    </template>
                    Préparer une déclaration
                </Button>
            </EmptyState>

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
                                    dropdown-in-flow
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

            <CompanyYearPickerModal
                v-model:open="pickerOpen"
                target="fiscal"
                default-year="previous"
                title="Préparer une déclaration"
                description="Choisissez l'entreprise et l'exercice à déclarer."
                submit-label="Ouvrir la fiscalité"
                year-label="Année fiscale"
            />
        </div>
    </UserLayout>
</template>
