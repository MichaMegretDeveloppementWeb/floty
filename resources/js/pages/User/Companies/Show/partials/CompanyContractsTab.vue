<script setup lang="ts">
/**
 * Onglet « Contrats » de la page Show Company (chantier N.1 + N.1.fixes).
 *
 * UX raffinée (chantier N.1.fixes) :
 * - Stats contextuelles sous le titre (jours cumulés + répartition LCD/LLD),
 *   refletant le filtre actif.
 * - Pills d'années cliquables (1 clic = année complète) · scalable jusqu'à
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
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import Button from '@/Components/Ui/Button/Button.vue';
import Card from '@/Components/Ui/Card/Card.vue';
import DateRangePicker from '@/Components/Ui/DateRangePicker/DateRangePicker.vue';
import Paginator from '@/Components/Ui/Paginator/Paginator.vue';
import { useCompanyContractsTable } from '@/Composables/Company/Show/useCompanyContractsTable';
import { injectCompanyTabsState } from '@/Composables/Company/Show/useCompanyTabs';
import CompanyContractsActiveFilterChip from './CompanyContractsActiveFilterChip.vue';
import CompanyContractsTable from './CompanyContractsTable.vue';
import YearPills from "@/Components/Ui/YearPills/YearPills.vue";


const props = defineProps<{
    company: App.Data.User.Company.CompanyDetailData;
    contracts: App.Data.User.Contract.PaginatedContractListData;
    contractsQuery: App.Data.User.Contract.ContractIndexQueryData;
    contractsStats: App.Data.User.Company.CompanyContractsStatsData;
    contractsAvailableYears: number[];
}>();

const tableState = useCompanyContractsTable({ query: props.contractsQuery });
const tabsState = injectCompanyTabsState();

/**
 * D5.10.U/V · marque Fiscalité et Facturation comme stales · ils
 * tireront leurs props au prochain `setTab(...)` au lieu de servir des
 * données pour l'ancienne année. Appelé après tout changement de
 * période sur Contrats (pill année ou plage custom).
 */
function invalidateYearDependentTabs(): void {
    tabsState?.markStale(['fiscal', 'billing']);
}

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
        // D5.10.U · plage custom · ne touche PAS `?year=` qui reste
        // l'exercice partagé entre onglets. Backend priorise
        // `periodStart`/`periodEnd` quand présentes (cf.
        // `ContractIndexQueryData::effectivePeriod()`).
        tableState.state.patchFilters({
            periodStart: range.startDate,
            periodEnd: range.endDate,
        });
    },
});
const periodOngoing = ref<boolean>(false);

// Année active (highlight pill correspondante) :
//   - Si plage custom active → null (chip custom à la place).
//   - Sinon prioritairement le filtre `year` partagé (D5.10.U).
//   - Sinon legacy : période = exercice complet `YYYY-01-01..YYYY-12-31`.
const activeYear = computed<number | null>(() => {
    const start = tableState.state.filters.value.periodStart;
    const end = tableState.state.filters.value.periodEnd;

    // Plage custom active · pas de pill highlightée.
    if (start !== null || end !== null) {
        return null;
    }

    const yearFilter = tableState.state.filters.value.year;
    if (yearFilter !== null) {
        return yearFilter;
    }

    return null;
});

const pickerYear = computed<number>(() => {
    const yearFilter = tableState.state.filters.value.year;
    if (yearFilter !== null) {
        return yearFilter;
    }

    const start = tableState.state.filters.value.periodStart;

    if (start !== null) {
        return Number.parseInt(start.slice(0, 4), 10);
    }

    return new Date().getFullYear();
});

const hasActivePeriodFilter = computed<boolean>(
    () =>
        tableState.state.filters.value.year !== null
        || tableState.state.filters.value.periodStart !== null
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

    return `${total} location${total > 1 ? 's' : ''}`;
});

const totalDaysLabel = computed<string>(() => {
    const days = props.contractsStats.totalDays;

    return `${days} jour${days > 1 ? 's' : ''} cumulé${days > 1 ? 's' : ''}`;
});

function selectYear(year: number): void {
    // D5.10.U · pousse `?year=` (unifié avec Fiscalité/Facturation) ·
    // efface toute plage custom active. Le backend dérive
    // `periodStart`/`periodEnd` via `ContractIndexQueryData::
    // effectivePeriod()` quand `year` est présent.
    tableState.state.patchFilters({
        year,
        periodStart: null,
        periodEnd: null,
    });
    invalidateYearDependentTabs();
}

// Mémorise la dernière année sélectionnée pour la restaurer quand
// l'utilisateur efface une période custom via le X du chip. Évite que
// le clear ne fasse retomber sur l'année courante (default backend) en
// perdant le contexte si l'utilisateur était sur une autre année.
const lastSelectedYear = ref<number | null>(activeYear.value);

watch(activeYear, (newVal) => {
    if (newVal !== null) {
        lastSelectedYear.value = newVal;
    }
});

function clearPeriod(): void {
    // D5.10.U · garde l'exercice partagé `?year=` actif (ou retombe sur
    // le fallback) et efface uniquement la plage custom. Évite que le
    // X du chip ne touche au year partagé entre les onglets.
    const fallbackYear = tableState.state.filters.value.year
        ?? lastSelectedYear.value
        ?? props.company.currentRealYear;
    tableState.state.patchFilters({
        year: fallbackYear,
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
