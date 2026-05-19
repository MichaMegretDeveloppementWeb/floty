import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import { useCompanyContractsTable } from '@/Composables/Company/Show/useCompanyContractsTable';
import { injectCompanyTabsState } from '@/Composables/Company/Show/useCompanyTabs';

/**
 * Business logic for the Contracts tab of the Company Show page.
 *
 * Covers: custom-period popover state, global listeners (mousedown outside + Escape),
 * derived active period/year, select/clear year actions, invalidation of year-dependent
 * tabs (Fiscal + Billing), and stats labels.
 *
 * The partial stays presentational, destructuring the return and wiring v-model / @event in the template.
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
     * Marks Fiscalité and Facturation as stale so they refetch on the next `setTab(...)`
     * instead of serving data for the previous year. Called after any period change on Contrats.
     */
    function invalidateYearDependentTabs(): void {
        tabsState?.markStale(['fiscal', 'billing']);
    }

    // Custom-period popover state + global listeners (outside click / Escape) to close it.
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
            // Custom range does NOT touch `?year=`, which stays the cross-tab shared exercise.
            // Backend prioritises `periodStart`/`periodEnd` when present
            // (see `ContractIndexQueryData::effectivePeriod()`).
            tableState.state.patchFilters({
                periodStart: range.startDate,
                periodEnd: range.endDate,
            });
        },
    });
    const periodOngoing = ref<boolean>(false);

    // Active year (highlighted pill):
    //   - custom range active → null (custom chip takes over)
    //   - otherwise the shared `year` filter takes priority
    const activeYear = computed<number | null>(() => {
        const start = tableState.state.filters.value.periodStart;
        const end = tableState.state.filters.value.periodEnd;

        // Custom range active: no pill is highlighted.
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
        // Pushes `?year=` (unified with Fiscalité/Facturation) and clears any active custom range.
        // Backend derives `periodStart`/`periodEnd` via `ContractIndexQueryData::effectivePeriod()` when `year` is present.
        tableState.state.patchFilters({
            year,
            periodStart: null,
            periodEnd: null,
        });
        invalidateYearDependentTabs();
    }

    // Remembers the last selected year so we can restore it when the user clears
    // a custom period via the chip X. Prevents the clear from falling back to the
    // backend default current year and losing context.
    const lastSelectedYear = ref<number | null>(activeYear.value);

    watch(activeYear, (newVal) => {
        if (newVal !== null) {
            lastSelectedYear.value = newVal;
        }
    });

    function clearPeriod(): void {
        // Keeps the shared `?year=` exercise active (or falls back) and only clears the custom range.
        // Prevents the chip X from touching the year shared across tabs.
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
