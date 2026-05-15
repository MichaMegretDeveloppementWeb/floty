<script setup lang="ts">
/**
 * Onglet « Contrats » de la page Show Company (chantier N.1 + N.1.fixes).
 *
 * UX · stats contextuelles + pills années + popover période custom +
 * chip filtre actif dismissible. Server-side strict (ADR-0020) via
 * `useCompanyContractsTable`. Filtre période local à cet onglet
 * (ADR-0020 D3 « sélecteurs indépendants par section »).
 *
 * Pure présentation depuis Lot 7 D01 · toute la logique est extraite
 * dans `useCompanyContractsTab` (R9 + mémoire `feedback_vue_composables_extraction`).
 */
import { CalendarDays } from 'lucide-vue-next';
import { computed } from 'vue';
import Button from '@/Components/Ui/Button/Button.vue';
import DateRangePicker from '@/Components/Ui/DateRangePicker/DateRangePicker.vue';
import Paginator from '@/Components/Ui/Paginator/Paginator.vue';
import { useCompanyContractsTab } from '@/Composables/Company/Show/useCompanyContractsTab';
import CompanyContractsActiveFilterChip from './CompanyContractsActiveFilterChip.vue';
import CompanyContractsTable from './CompanyContractsTable.vue';

const props = defineProps<{
    company: App.Data.User.Company.CompanyDetailData;
    contracts: App.Data.User.Contract.PaginatedContractListData;
    contractsQuery: App.Data.User.Contract.ContractIndexQueryData;
    contractsStats: App.Data.User.Company.CompanyContractsStatsData;
    contractsAvailableYears: number[];
}>();

/**
 * Refonte D5.10.W · tri DESC pour rendu underline tabs (plus récent
 * à gauche, convention dashboard).
 */
const yearsDescending = computed<readonly number[]>(
    () => [...props.contractsAvailableYears].sort((a, b) => b - a),
);

const {
    tableState,
    periodPopoverOpen,
    popoverRoot,
    periodRange,
    periodOngoing,
    activeYear,
    pickerYear,
    hasActivePeriodFilter,
    isFilteredEmpty,
    isUnfilteredEmpty,
    totalContractsLabel,
    totalDaysLabel,
    selectYear,
    clearPeriod,
} = useCompanyContractsTab(props);
</script>

<template>
    <div class="flex flex-col gap-6">
        <!--
            Header éditorial · eyebrow + h2 + meta inline + ligne actions
            (year pills + popover période personnalisée). Plus de Card
            wrapping · alignement sur le pattern Linear-éditorial des
            autres tabs (refonte D5.10.W).
        -->
        <header class="flex flex-col gap-3">
            <h2 class="text-[28px] font-semibold leading-none tracking-tight text-slate-900">
                Locations
            </h2>
            <p class="text-sm text-slate-500">
                <span>{{ totalContractsLabel }}</span>
                <template v-if="props.contracts.meta.total > 0">
                    <span class="mx-1.5 text-slate-300">·</span>
                    <span>{{ totalDaysLabel }}</span>
                    <span class="mx-1.5 text-slate-300">·</span>
                    <span>
                        {{ props.contractsStats.lcdCount }} LCD /
                        {{ props.contractsStats.lldCount }} LLD
                    </span>
                </template>
                <span
                    v-if="hasActivePeriodFilter"
                    class="ml-1 text-slate-400"
                >
                    (période sélectionnée)
                </span>
            </p>

            <div
                v-if="props.contractsAvailableYears.length > 0"
                class="flex flex-col gap-3 border-b border-slate-100 lg:flex-row lg:items-end lg:justify-between"
            >
                <!--
                    Year tabs underline · pattern miroir des onglets
                    Fiscalité/Facturation refondus. Au clic, switch
                    le filtre exercice en cours. `activeYear === null`
                    quand une période personnalisée est active · aucun
                    tab n'est highlightée dans ce cas (la chip de filtre
                    custom indique l'état).
                -->
                <nav
                    class="flex gap-6"
                    aria-label="Filtre par exercice"
                >
                    <button
                        v-for="year in yearsDescending"
                        :key="year"
                        type="button"
                        :class="[
                            '-mb-px cursor-pointer border-b-2 pb-3 text-sm font-medium transition-colors duration-[120ms]',
                            activeYear === year
                                ? 'border-slate-900 text-slate-900'
                                : 'border-transparent text-slate-500 hover:text-slate-900',
                        ]"
                        @click="selectYear(year)"
                    >
                        {{ year }}
                    </button>
                </nav>

                <div ref="popoverRoot" class="relative shrink-0 pb-3">
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
                        Période personnalisée
                    </Button>

                    <!--
                        Mobile (< sm) · bottom sheet centré.
                        Desktop (≥ sm) · popover ancré sous le bouton.
                        Aligné sur le pattern FilterPopover du projet.
                    -->
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

            <!--
                Chip de filtre actif (smart label) · masqué quand une
                année pleine est sélectionnée (la pill correspondante
                est déjà highlightée, le chip serait redondant).
            -->
            <div v-if="hasActivePeriodFilter && activeYear === null">
                <CompanyContractsActiveFilterChip
                    :period-start="
                        tableState.state.filters.value.periodStart
                    "
                    :period-end="tableState.state.filters.value.periodEnd"
                    @clear="clearPeriod"
                />
            </div>
        </header>

        <p v-if="isUnfilteredEmpty" class="text-sm text-slate-500">
            Aucune location n'a encore été enregistrée pour cette entreprise.
        </p>

        <p v-else-if="isFilteredEmpty" class="text-sm text-slate-500">
            Aucune location sur la période sélectionnée. Modifiez ou retirez
            le filtre période pour voir les autres locations.
        </p>

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
