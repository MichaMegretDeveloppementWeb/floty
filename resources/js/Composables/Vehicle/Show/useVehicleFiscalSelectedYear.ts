/**
 * Sync de l'année sélectionnée sur l'onglet Fiscalité de la fiche
 * Vehicle Show.
 *
 * **D5.10.U** · param URL unifié `?year=` partagé entre Fiscalité et
 * Facturation.
 * **D5.10.V** · le partial reload ne tire QUE les props de Fiscal ·
 * Billing est marqué stale (re-fetch au prochain clic).
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
