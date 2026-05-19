/**
 * Hybrid year/period scope selector for the Contracts Index.
 *
 * Two URL-side mutually exclusive modes:
 *  - `'year'`   → `?year=YYYY` (compact SelectInput)
 *  - `'period'` → `?periodStart=&periodEnd=` (DateRangePicker popover)
 *
 * Initial mode derived from URL params (`year` → year; `periodStart/End` → period; else `year`).
 *
 * Also manages: period popover open state + click-outside + Escape,
 * the pill button label, and `pickerYear` (year used to anchor the DateRangePicker).
 *
 * The return value is consumed as-is by `pages/User/Contracts/Index/Index.vue`.
 */

import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import type { ComputedRef, Ref, WritableComputedRef } from 'vue';
import type { ContractFilters } from '@/Composables/Contract/Index/useContractsTable';
import type { ServerTableState } from '@/Composables/Shared/useServerTableState';
import { formatDateFr } from '@/Utils/format/formatDateFr';

export type ScopeMode = 'year' | 'period';

export type YearOption = { value: number; label: string };

export type PeriodRange = { startDate: string | null; endDate: string | null };

export type UseContractsTimeScopeOptions = {
    /** Initial filters from the backend query DTO, used to resolve the initial mode. */
    initialQuery: Pick<ContractFilters, 'year' | 'periodStart' | 'periodEnd'>;
    /** Available years driving `defaultYear` and `yearOptions`. */
    availableYears: ComputedRef<readonly number[]>;
    /** Access to time filters within `useServerTableState`. */
    tableState: ServerTableState<ContractFilters>;
};

export type UseContractsTimeScopeReturn = {
    scopeMode: Ref<ScopeMode>;
    setScopeMode: (mode: ScopeMode) => void;
    yearOptions: ComputedRef<YearOption[]>;
    defaultYear: ComputedRef<number>;
    yearModel: WritableComputedRef<number>;
    periodRange: WritableComputedRef<PeriodRange>;
    periodOngoing: Ref<boolean>;
    pickerYear: ComputedRef<number>;
    periodLabel: ComputedRef<string>;
    periodPopoverOpen: Ref<boolean>;
    popoverRoot: Ref<HTMLElement | null>;
};

/**
 * Resolves the initial mode from URL params hydrated on the backend query DTO.
 */
export function resolveInitialScopeMode(
    initialQuery: Pick<ContractFilters, 'year' | 'periodStart' | 'periodEnd'>,
): ScopeMode {
    if (initialQuery.year !== null) {
        return 'year';
    }

    if (initialQuery.periodStart !== null || initialQuery.periodEnd !== null) {
        return 'period';
    }

    return 'year';
}

export function useContractsTimeScope(
    options: UseContractsTimeScopeOptions,
): UseContractsTimeScopeReturn {
    const { initialQuery, availableYears, tableState } = options;

    const scopeMode = ref<ScopeMode>(resolveInitialScopeMode(initialQuery));
    const periodOngoing = ref<boolean>(false);
    const periodPopoverOpen = ref<boolean>(false);
    const popoverRoot = ref<HTMLElement | null>(null);

    const yearOptions = computed<YearOption[]>(() =>
        availableYears.value.map((year) => ({ value: year, label: String(year) })),
    );

    const defaultYear = computed<number>(() => {
        if (initialQuery.year !== null) {
            return initialQuery.year;
        }

        const years = availableYears.value;

        return years.length === 0
            ? new Date().getFullYear()
            : Math.max(...years);
    });

    const yearModel = computed<number>({
        get: () => tableState.filters.value.year ?? defaultYear.value,
        set: (v: number) => {
            // In year mode, set year and clear periodStart/End. patchFilters does it atomically in 1 reload.
            tableState.patchFilters({
                year: v,
                periodStart: null,
                periodEnd: null,
            });
        },
    });

    const periodRange = computed<PeriodRange>({
        get: () => ({
            startDate: tableState.filters.value.periodStart,
            endDate: tableState.filters.value.periodEnd,
        }),
        set: (range: PeriodRange) => {
            tableState.patchFilters({
                year: null,
                periodStart: range.startDate,
                periodEnd: range.endDate,
            });
        },
    });

    const pickerYear = computed<number>(() => {
        const start = tableState.filters.value.periodStart;

        if (start !== null) {
            return Number.parseInt(start.slice(0, 4), 10);
        }

        return defaultYear.value;
    });

    const periodLabel = computed<string>(() => {
        const start = tableState.filters.value.periodStart;
        const end = tableState.filters.value.periodEnd;

        if (start === null && end === null) {
            return 'Aucune période sélectionnée';
        }

        const s = start === null ? '…' : formatDateFr(start);
        const e = end === null ? '…' : formatDateFr(end);

        return `${s} → ${e}`;
    });

    function setScopeMode(mode: ScopeMode): void {
        scopeMode.value = mode;

        if (mode === 'year') {
            // Switch to year: apply the default year, clear period, close the popover.
            periodPopoverOpen.value = false;

            if (tableState.filters.value.year === null) {
                tableState.patchFilters({
                    year: defaultYear.value,
                    periodStart: null,
                    periodEnd: null,
                });
            }
        } else {
            // Switch to period: keep period if already entered, otherwise just clear year.
            if (
                tableState.filters.value.periodStart === null
                && tableState.filters.value.periodEnd === null
            ) {
                tableState.patchFilters({
                    year: null,
                    periodStart: null,
                    periodEnd: null,
                });
            } else {
                tableState.setFilter('year', null);
            }

            // Auto-open the date picker when switching to period mode: it's the next logical gesture
            // and the popover closes easily (click outside / Escape).
            periodPopoverOpen.value = true;
        }
    }

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

    return {
        scopeMode,
        setScopeMode,
        yearOptions,
        defaultYear,
        yearModel,
        periodRange,
        periodOngoing,
        pickerYear,
        periodLabel,
        periodPopoverOpen,
        popoverRoot,
    };
}
