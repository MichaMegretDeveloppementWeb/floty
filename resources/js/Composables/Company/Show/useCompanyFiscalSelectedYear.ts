/**
 * Sync of the selected year on the Fiscal tab of the Company Show page.
 *
 * Unified URL param `?year=` shared across tabs. The partial reload pulls ONLY
 * the current Fiscal tab props; other year-dependent tabs (`billing`, `contracts`)
 * are marked stale via `markStale(...)` so they refetch on the next click.
 *
 * `pendingDeclarations` / `pendingInvoices` are cross-year and are not reloaded.
 */

import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import type { Ref } from 'vue';
import { injectCompanyTabsState } from '@/Composables/Company/Show/useCompanyTabs';

export function useCompanyFiscalSelectedYear(
    initialYear: number,
): {
    selectedYear: Ref<number>;
    selectYear: (year: number) => void;
    loading: Ref<boolean>;
} {
    const selectedYear = ref<number>(initialYear);
    const loading = ref<boolean>(false);
    const tabsState = injectCompanyTabsState();

    function selectYear(year: number): void {
        if (year === selectedYear.value || loading.value) {
            return;
        }

        selectedYear.value = year;
        loading.value = true;

        const url = new URL(window.location.href);
        url.searchParams.set('year', String(year));
        // Clear any leftover custom Contracts filter so switching to Contracts
        // does not fall back on a range from the previous exercise.
        url.searchParams.delete('periodStart');
        url.searchParams.delete('periodEnd');

        router.get(
            url.pathname + url.search,
            {},
            {
                only: [
                    'companyFiscal',
                    'declarationLifecycle',
                    'billingYear',
                ],
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onSuccess: () => {
                    // Year-dependent tabs invalidated: they will refetch on the next `setTab(...)`.
                    tabsState?.markStale(['billing', 'contracts']);
                },
                onFinish: () => {
                    loading.value = false;
                },
            },
        );
    }

    return { selectedYear, selectYear, loading };
}
