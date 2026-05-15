import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import { useCompanyContractsTable } from '@/Composables/Company/Show/useCompanyContractsTable';
import { injectCompanyTabsState } from '@/Composables/Company/Show/useCompanyTabs';

/**
 * Logique métier de l'onglet « Contrats » de la page Show Company
 * (Lot 7 D01 · F-40-003 · extraction depuis `CompanyContractsTab.vue`
 * qui violait R9 + mémoire `feedback_vue_composables_extraction`).
 *
 * Concentre · état popover période custom · listeners global
 * (mousedown outside + Escape) · état dérivé période/année active ·
 * actions select/clear année · invalidation cache onglets
 * year-dépendants (Fiscal + Billing) · stats labels.
 *
 * Le partial reste pure présentation après extraction · destructure
 * le retour, branche v-model et @event sur le template.
 */
export type UseCompanyContractsTabOptions = {
    company: App.Data.User.Company.CompanyDetailData;
    contracts: App.Data.User.Contract.PaginatedContractListData;
    contractsQuery: App.Data.User.Contract.ContractIndexQueryData;
    contractsStats: App.Data.User.Company.CompanyContractsStatsData;
    contractsAvailableYears: number[];
};

export type UseCompanyContractsTabReturn = {
    tableState: ReturnType<typeof useCompanyContractsTable>;
    periodPopoverOpen: Ref<boolean>;
    popoverRoot: Ref<HTMLElement | null>;
    periodRange: ReturnType<typeof computed<{ startDate: string | null; endDate: string | null }>>;
    periodOngoing: Ref<boolean>;
    activeYear: ComputedRef<number | null>;
    pickerYear: ComputedRef<number>;
    hasActivePeriodFilter: ComputedRef<boolean>;
    isFilteredEmpty: ComputedRef<boolean>;
    isUnfilteredEmpty: ComputedRef<boolean>;
    totalContractsLabel: ComputedRef<string>;
    totalDaysLabel: ComputedRef<string>;
    selectYear: (year: number) => void;
    clearPeriod: () => void;
};

export function useCompanyContractsTab(opts: UseCompanyContractsTabOptions): UseCompanyContractsTabReturn {
    const tableState = useCompanyContractsTable({ query: opts.contractsQuery });
    const tabsState = injectCompanyTabsState();

    /**
     * D5.10.U/V · marque Fiscalité et Facturation comme stales · ils
     * tireront leurs props au prochain `setTab(...)` au lieu de servir
     * des données pour l'ancienne année. Appelé après tout changement
     * de période sur Contrats (pill année ou plage custom).
     */
    function invalidateYearDependentTabs(): void {
        tabsState?.markStale(['fiscal', 'billing']);
    }

    // État popover "Période personnalisée" + listeners globaux pour
    // fermer au clic extérieur ou Escape.
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

    // Année active (highlight pill correspondante) ·
    //   - Si plage custom active → null (chip custom à la place).
    //   - Sinon prioritairement le filtre `year` partagé (D5.10.U).
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
        () => opts.contracts.meta.total === 0 && hasActivePeriodFilter.value,
    );

    const isUnfilteredEmpty = computed<boolean>(
        () => opts.contracts.meta.total === 0 && !hasActivePeriodFilter.value,
    );

    const totalContractsLabel = computed<string>(() => {
        const total = opts.contracts.meta.total;

        return `${total} location${total > 1 ? 's' : ''}`;
    });

    const totalDaysLabel = computed<string>(() => {
        const days = opts.contractsStats.totalDays;

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
    // l'utilisateur efface une période custom via le X du chip. Évite
    // que le clear ne fasse retomber sur l'année courante (default
    // backend) en perdant le contexte si l'utilisateur était sur une
    // autre année.
    const lastSelectedYear = ref<number | null>(activeYear.value);

    watch(activeYear, (newVal) => {
        if (newVal !== null) {
            lastSelectedYear.value = newVal;
        }
    });

    function clearPeriod(): void {
        // D5.10.U · garde l'exercice partagé `?year=` actif (ou retombe
        // sur le fallback) et efface uniquement la plage custom. Évite
        // que le X du chip ne touche au year partagé entre les onglets.
        const fallbackYear = tableState.state.filters.value.year
            ?? lastSelectedYear.value
            ?? opts.company.currentRealYear;
        tableState.state.patchFilters({
            year: fallbackYear,
            periodStart: null,
            periodEnd: null,
        });
    }

    return {
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
    };
}
