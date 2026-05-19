/**
 * Page-local year selector (ADR-0020).
 *
 * Each consuming page (Dashboard, Planning, FiscalRules, Vehicles/Companies/Contracts Index)
 * manages its own year via `?year=YYYY`. This composable wraps the Inertia partial reload
 * while preserving other query params (filters, sort, pagination, etc.).
 *
 * For Index pages using `useServerTableState`, pass `year` via `serializeFilters` for consistency.
 * For standalone pages (Dashboard, FiscalRules), this composable is the direct API.
 */

import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import type { Ref } from 'vue';

export function useLocalYearSelector(
    initialYear: number,
    only: readonly string[],
    options: { onSuccess?: () => void } = {},
): {
    selectedYear: Ref<number>;
    selectYear: (year: number) => void;
} {
    const selectedYear = ref<number>(initialYear);

    function selectYear(year: number): void {
        if (year === selectedYear.value) {
            return;
        }

        selectedYear.value = year;

        // Preserves other query params by building the URL from the current one
        // (do not use router.get(pathname, params) which clears everything).
        const url = new URL(window.location.href);
        url.searchParams.set('year', String(year));

        router.get(
            url.pathname + url.search,
            {},
            {
                only: [...only],
                preserveState: true,
                preserveScroll: true,
                replace: true,
                // Runs after Inertia updates the URL + props. Used by pages combining a
                // `useLocalYearSelector` with a separate `Inertia::defer` (e.g. Planning) to chain
                // a partial reload of the deferred prop ON THE NEW URL.
                onSuccess: options.onSuccess,
            },
        );
    }

    return { selectedYear, selectYear };
}
