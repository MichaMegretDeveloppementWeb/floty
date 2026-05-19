/**
 * High-level façade for a page-local year selector, foundation of the temporal doctrine.
 *
 * Responsibilities:
 *   - Exposes the year scope (`currentYear`, `minYear`, `availableYears`) from the
 *     {@link App.Data.Shared.YearScopeData} prop DTO.
 *   - Owns mutable `selectedYear`, initialised to `currentYear` (or `opts.initialYear` override).
 *   - Mutates via `selectYear()` with validation against `availableYears`.
 *
 * Two modes depending on the consuming page:
 *
 *   1. Reload mode (`opts.reloadKeys` set): for Index/Show pages that must reload data from the backend
 *      (Vehicles Index, Vehicle Show, Planning, FiscalRules...). Delegates to `useLocalYearSelector`,
 *      which calls `router.get()` with a partial reload.
 *
 *   2. Local mode (`opts` omitted or `reloadKeys` empty): for sections where all years are pre-computed
 *      front-side (e.g. Activity section of the Company page where `activityByYear` already contains all years).
 *      URL is synced via `window.history.replaceState`, no Inertia reload.
 *
 * Companion component: {@link YearSelector} (presentational).
 */

import { computed, ref } from 'vue';
import type { ComputedRef, Ref, WritableComputedRef } from 'vue';
import { useLocalYearSelector } from './useLocalYearSelector';

type YearScope = App.Data.Shared.YearScopeData;

export type UseYearScopeReturn = {
    /** Current calendar year (Present). Not mutable from the UI. */
    currentYear: ComputedRef<number>;
    /** Global lower bound of the selector. */
    minYear: ComputedRef<number>;
    /** Continuous range `[minYear, ..., max]`. */
    availableYears: ComputedRef<readonly number[]>;
    /**
     * Currently selected year. Prefer reading via {@see selectedYearModel} which always goes
     * through {@see selectYear} (validation + URL/reload sync) when used as a `v-model`.
     */
    selectedYear: Ref<number>;
    /**
     * `v-model` wrapper around `selectedYear`. Setter goes through `selectYear()` rather than mutating
     * `selectedYear.value` directly, so URL sync (local mode) or partial reload (reload mode) always fire,
     * even when the mutation comes from a `v-model` binding.
     */
    selectedYearModel: WritableComputedRef<number>;
    /** `true` iff `availableYears.length > 1` (otherwise the selector is frozen). */
    canSelect: ComputedRef<boolean>;
    /** `true` iff `year` ∈ `availableYears`. */
    isInScope: (year: number) => boolean;
    /**
     * Mutates `selectedYear`. No-op when the year is out of scope or unchanged.
     * Triggers either an Inertia partial reload (reload mode) or a silent URL replace (local mode).
     */
    selectYear: (year: number) => void;
};

export type UseYearScopeOptions = {
    /**
     * Inertia keys to reload on year change (passed to `router.get(..., { only: [...] })`).
     * Omitted/empty → local mode (silent URL replace, no reload).
     */
    reloadKeys?: readonly string[];
    /**
     * Initial mount year. Defaults to `scope.currentYear`.
     * Must be in `scope.availableYears` (otherwise fallback to `currentYear`).
     */
    initialYear?: number;
};

export function useYearScope(
    scope: YearScope,
    opts: UseYearScopeOptions = {},
): UseYearScopeReturn {
    const currentYear = computed<number>(() => scope.currentYear);
    const minYear = computed<number>(() => scope.minYear);
    const availableYears = computed<readonly number[]>(() => scope.availableYears);

    const isInScope = (year: number): boolean =>
        scope.availableYears.includes(year);

    // Preserves deep-link / F5 refresh: if the page is opened with `?year=YYYY` in the URL
    // (shared link or F5 after switching), initialise on that value. Explicit `opts.initialYear`
    // remains the priority (advanced use), falling back to `scope.currentYear` otherwise.
    function readYearFromUrl(): number | undefined {
        if (typeof window === 'undefined') {
            return undefined;
        }

        const raw = new URL(window.location.href).searchParams.get('year');

        if (raw === null) {
            return undefined;
        }

        const parsed = Number(raw);

        return Number.isFinite(parsed) ? parsed : undefined;
    }

    const candidate = opts.initialYear ?? readYearFromUrl();

    const initial =
        candidate !== undefined && isInScope(candidate)
            ? candidate
            : scope.currentYear;

    const useReload = (opts.reloadKeys?.length ?? 0) > 0;

    // Reload mode: delegate to useLocalYearSelector (partial reload + URL).
    // Local mode: handle the silent URL replace here.
    const reloadDelegate = useReload
        ? useLocalYearSelector(initial, opts.reloadKeys ?? [])
        : null;

    const selectedYear: Ref<number> =
        reloadDelegate?.selectedYear ?? ref<number>(initial);

    const canSelect = computed<boolean>(() => scope.availableYears.length > 1);

    function selectYear(year: number): void {
        if (year === selectedYear.value) {
            return;
        }

        if (!isInScope(year)) {
            return;
        }

        if (reloadDelegate !== null) {
            reloadDelegate.selectYear(year);

            return;
        }

        // Local mode: mutate + silent URL replace (same pattern as useCompanySelectedYear, no Inertia reload).
        selectedYear.value = year;

        if (typeof window !== 'undefined') {
            const url = new URL(window.location.href);
            url.searchParams.set('year', String(year));
            window.history.replaceState({}, '', url.toString());
        }
    }

    // v-model wrapper: always goes through selectYear() so validation + URL/reload sync fire
    // even when the mutation comes from a Vue `v-model` binding (a direct set to selectedYear.value
    // would bypass selectYear).
    const selectedYearModel = computed<number>({
        get: () => selectedYear.value,
        set: (value: number) => selectYear(value),
    });

    return {
        currentYear,
        minYear,
        availableYears,
        selectedYear,
        selectedYearModel,
        canSelect,
        isInScope,
        selectYear,
    };
}
