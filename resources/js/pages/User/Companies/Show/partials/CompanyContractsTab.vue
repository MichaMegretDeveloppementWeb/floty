<script setup lang="ts">
/**
 * Onglet « Contrats » de la page Show Company (chantier N.1).
 *
 * Server-side strict (cf. ADR-0020) : pagination + tri + filtre période
 * piloté par `useCompanyContractsTable`. Aucun filtrage côté client.
 *
 * Filtre période **local** à cet onglet (pas de sélecteur global —
 * pose la première brique de la migration ADR-0020 D3 « sélecteurs
 * indépendants par section »).
 */
import { computed, ref } from 'vue';
import Card from '@/Components/Ui/Card/Card.vue';
import DateRangePicker from '@/Components/Ui/DateRangePicker/DateRangePicker.vue';
import FieldLabel from '@/Components/Ui/FieldLabel/FieldLabel.vue';
import Paginator from '@/Components/Ui/Paginator/Paginator.vue';
import { useCompanyContractsTable } from '@/Composables/Company/Show/useCompanyContractsTable';
import CompanyContractsTable from './CompanyContractsTable.vue';

const props = defineProps<{
    company: App.Data.User.Company.CompanyDetailData;
    contracts: App.Data.User.Contract.PaginatedContractListData;
    contractsQuery: App.Data.User.Contract.ContractIndexQueryData;
}>();

const tableState = useCompanyContractsTable({ query: props.contractsQuery });

const periodRange = computed({
    get: () => ({
        startDate: tableState.state.filters.value.periodStart,
        endDate: tableState.state.filters.value.periodEnd,
    }),
    set: (range: { startDate: string | null; endDate: string | null }) => {
        // patchFilters : update atomique en 1 seul reload (anti-race
        // documentée dans `useServerTableState`).
        tableState.state.patchFilters({
            periodStart: range.startDate,
            periodEnd: range.endDate,
        });
    },
});
const periodOngoing = ref<boolean>(false);

// Année d'ouverture du DateRangePicker. Indépendante du sélecteur d'année
// global (décision user, ADR-0020 D3) : si un filtre période est déjà
// actif, on ouvre sur l'année du début ; sinon année calendaire courante.
const pickerYear = computed<number>(() => {
    const start = tableState.state.filters.value.periodStart;
    if (start !== null) {
        return Number.parseInt(start.slice(0, 4), 10);
    }

    return new Date().getFullYear();
});

const hasActivePeriodFilter = computed<boolean>(
    () =>
        tableState.state.filters.value.periodStart !== null
        || tableState.state.filters.value.periodEnd !== null,
);

const isFilteredEmpty = computed<boolean>(
    () => props.contracts.meta.total === 0 && hasActivePeriodFilter.value,
);

const isUnfilteredEmpty = computed<boolean>(
    () => props.contracts.meta.total === 0 && !hasActivePeriodFilter.value,
);
</script>

<template>
    <div class="flex flex-col gap-6">
        <!-- Card filtres + compteur -->
        <Card>
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">
                        Contrats
                    </h3>
                    <p class="text-sm text-slate-500">
                        {{ props.contracts.meta.total }} contrat(s)
                        <span v-if="hasActivePeriodFilter">
                            sur la période sélectionnée
                        </span>
                        <span v-else>au total</span>
                    </p>
                </div>
                <div class="w-full sm:w-auto">
                    <FieldLabel for="contracts-period">
                        Filtre période
                    </FieldLabel>
                    <DateRangePicker
                        id="contracts-period"
                        v-model:range="periodRange"
                        v-model:ongoing="periodOngoing"
                        :year="pickerYear"
                    />
                </div>
            </div>
        </Card>

        <!-- Empty state non filtré -->
        <Card v-if="isUnfilteredEmpty">
            <p class="text-sm text-slate-500">
                Aucun contrat n'a encore été enregistré pour cette entreprise.
            </p>
        </Card>

        <!-- Empty state filtré -->
        <Card v-else-if="isFilteredEmpty">
            <p class="text-sm text-slate-500">
                Aucun contrat sur la période sélectionnée. Modifiez ou retirez
                le filtre période pour voir les autres contrats.
            </p>
        </Card>

        <!-- Table + paginator -->
        <template v-else>
            <CompanyContractsTable
                :contracts="props.contracts.data"
                :columns="tableState.columns"
                :active-sort-column-key="tableState.activeSortColumnKey.value"
                :sort-direction="tableState.state.sort.value.direction"
                :badge-tone="tableState.badgeTone"
                :short-label="tableState.shortLabel"
                @header-click="tableState.onHeaderClick"
                @row-click="tableState.onRowClick"
            />

            <Paginator
                :meta="props.contracts.meta"
                @page-change="tableState.state.setPage"
                @per-page-change="tableState.state.setPerPage"
            />
        </template>
    </div>
</template>
