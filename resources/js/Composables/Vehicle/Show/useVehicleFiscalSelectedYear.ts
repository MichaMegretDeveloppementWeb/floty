/**
 * Sync of the selected year on the Fiscal tab of the Vehicle Show page.
 *
 * Unified URL param `?year=` shared between Fiscalité and Facturation.
 * The partial reload pulls ONLY the Fiscal props; Billing is marked stale and refetches on next click.
 */

import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import type { Ref } from 'vue';
import { injectVehicleTabsState } from '@/Composables/Vehicle/Show/useVehicleTabs';

export function useVehicleFiscalSelectedYear(
    initialYear: number,
): {
    selectedYear: Ref<number>;
    selectYear: (year: number) => void;
    loading: Ref<boolean>;
} {
    const selectedYear = ref<number>(initialYear);
    const loading = ref<boolean>(false);
    const tabsState = injectVehicleTabsState();

    function selectYear(year: number): void {
        if (year === selectedYear.value || loading.value) {
            return;
        }

        selectedYear.value = year;
        loading.value = true;

        const url = new URL(window.location.href);
        url.searchParams.set('year', String(year));

        router.get(
            url.pathname + url.search,
            {},
            {
                only: ['fiscalYearBreakdown', 'fiscalYear', 'billingYear'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onSuccess: () => {
                    tabsState?.markStale(['billing']);
                },
                onFinish: () => {
                    loading.value = false;
                },
            },
        );
    }

    return { selectedYear, selectYear, loading };
}
