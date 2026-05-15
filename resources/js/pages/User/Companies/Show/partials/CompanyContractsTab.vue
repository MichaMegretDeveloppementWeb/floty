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
import Button from '@/Components/Ui/Button/Button.vue';
import Card from '@/Components/Ui/Card/Card.vue';
import DateRangePicker from '@/Components/Ui/DateRangePicker/DateRangePicker.vue';
import Paginator from '@/Components/Ui/Paginator/Paginator.vue';
import YearPills from '@/Components/Ui/YearPills/YearPills.vue';
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
    <div class="flex flex-col gap-4">
        <!-- Header : titre + stats contextuelles + toolbar année -->
        <Card>
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-1">
                    <h3 class="text-base font-semibold text-slate-900">
                        Locations
                    </h3>
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
                </div>

                <div
                    v-if="props.contractsAvailableYears.length > 0"
                    class="flex flex-col gap-3 lg:flex-row lg:items-center"
                >
                    <div class="flex-1 min-w-0">
                        <YearPills
                            :years="props.contractsAvailableYears"
                            :active-year="activeYear"
                            @select="selectYear"
                        />
                    </div>

                    <div ref="popoverRoot" class="relative shrink-0">
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
                            Mobile (< sm) : bottom sheet centré.
                            Desktop (≥ sm) : popover ancré sous le bouton.
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
                    Chip de filtre actif (smart label).
                    Masqué quand une année pleine est sélectionnée · la pill
                    correspondante est déjà highlightée, le chip serait
                    redondant. N'apparaît que pour les périodes custom.
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
            </div>
        </Card>

        <Card v-if="isUnfilteredEmpty">
            <p class="text-sm text-slate-500">
                Aucune location n'a encore été enregistrée pour cette entreprise.
            </p>
        </Card>

        <Card v-else-if="isFilteredEmpty">
            <p class="text-sm text-slate-500">
                Aucune location sur la période sélectionnée. Modifiez ou retirez
                le filtre période pour voir les autres locations.
            </p>
        </Card>

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
