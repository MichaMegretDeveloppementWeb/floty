<script setup lang="ts">
/**
 * Onglet « Contrats » de la page Show Company (chantier N.1 + N.1.fixes).
 *
 * UX raffinée (chantier N.1.fixes) :
 * - Stats contextuelles sous le titre (jours cumulés + répartition LCD/LLD),
 *   refletant le filtre actif.
 * - Pills d'années cliquables (1 clic = année complète) — scalable jusqu'à
 *   N années via scroll horizontal.
 * - Bouton « Période personnalisée » ouvre le DateRangePicker dans un
 *   popover masqué par défaut (ne pollue plus l'écran en permanence).
 * - Chip de filtre actif dismissible (smart label : « Année 2024 » vs
 *   plage custom).
 *
 * Architecture (chantier N.1) :
 * - Server-side strict (cf. ADR-0020) via `useCompanyContractsTable`
 * - Filtre période **local** à cet onglet (ADR-0020 D3 « sélecteurs
 *   indépendants par section »)
 */
import { CalendarDays } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import Button from '@/Components/Ui/Button/Button.vue';
import Card from '@/Components/Ui/Card/Card.vue';
import DateRangePicker from '@/Components/Ui/DateRangePicker/DateRangePicker.vue';
import Paginator from '@/Components/Ui/Paginator/Paginator.vue';
import { useCompanyContractsTable } from '@/Composables/Company/Show/useCompanyContractsTable';
import CompanyContractsActiveFilterChip from './CompanyContractsActiveFilterChip.vue';
import CompanyContractsTable from './CompanyContractsTable.vue';
import CompanyContractsYearPills from './CompanyContractsYearPills.vue';

const props = defineProps<{
    company: App.Data.User.Company.CompanyDetailData;
    contracts: App.Data.User.Contract.PaginatedContractListData;
    contractsQuery: App.Data.User.Contract.ContractIndexQueryData;
    contractsStats: App.Data.User.Company.CompanyContractsStatsData;
    contractsAvailableYears: number[];
}>();

const tableState = useCompanyContractsTable({ query: props.contractsQuery });

// État popover "Période personnalisée"
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

const periodRange = computed({
    get: () => ({
        startDate: tableState.state.filters.value.periodStart,
        endDate: tableState.state.filters.value.periodEnd,
    }),
    set: (range: { startDate: string | null; endDate: string | null }) => {
        tableState.state.patchFilters({
            periodStart: range.startDate,
            periodEnd: range.endDate,
        });
    },
});
const periodOngoing = ref<boolean>(false);

// Année active : si la fenêtre filtrée correspond exactement à
// `YYYY-01-01..YYYY-12-31`, on considère cette année comme active
// (highlight la pill correspondante). Sinon null (filtre custom).
const activeYear = computed<number | null>(() => {
    const start = tableState.state.filters.value.periodStart;
    const end = tableState.state.filters.value.periodEnd;

    if (start === null || end === null) {
        return null;
    }

    const startMatch = /^(\d{4})-01-01$/.exec(start);
    const endMatch = /^(\d{4})-12-31$/.exec(end);

    if (startMatch === null || endMatch === null) {
        return null;
    }

    if (startMatch[1] !== endMatch[1]) {
        return null;
    }

    return Number.parseInt(startMatch[1], 10);
});

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

const totalContractsLabel = computed<string>(() => {
    const total = props.contracts.meta.total;
    return `${total} contrat${total > 1 ? 's' : ''}`;
});

const totalDaysLabel = computed<string>(() => {
    const days = props.contractsStats.totalDays;
    return `${days} jour${days > 1 ? 's' : ''} cumulé${days > 1 ? 's' : ''}`;
});

function selectYear(year: number): void {
    tableState.state.patchFilters({
        periodStart: `${year}-01-01`,
        periodEnd: `${year}-12-31`,
    });
}

function clearPeriod(): void {
    tableState.state.patchFilters({
        periodStart: null,
        periodEnd: null,
    });
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <!-- Header : titre + stats contextuelles + toolbar année -->
        <Card>
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-1">
                    <h3 class="text-base font-semibold text-slate-900">
                        Contrats
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
                        <CompanyContractsYearPills
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

                <!-- Chip de filtre actif (smart label) -->
                <div v-if="hasActivePeriodFilter">
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
                Aucun contrat n'a encore été enregistré pour cette entreprise.
            </p>
        </Card>

        <Card v-else-if="isFilteredEmpty">
            <p class="text-sm text-slate-500">
                Aucun contrat sur la période sélectionnée. Modifiez ou retirez
                le filtre période pour voir les autres contrats.
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
