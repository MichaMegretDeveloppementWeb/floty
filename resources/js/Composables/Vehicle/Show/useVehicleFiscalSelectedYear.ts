/**
 * Sync de l'année sélectionnée sur l'onglet Fiscalité de la fiche
 * Vehicle Show. Pattern strict ADR-0020 D3 : sélecteur **local et
 * indépendant**, jamais lié à un sélecteur global ni à celui d'un
 * autre onglet.
 *
 * Préfixe URL `?fiscalYear=` (aligné Company Fiscal) pour ne pas
 * collider avec `?billingYear=` (onglet Facturation) ni `?tab=`.
 *
 * Le partial reload Inertia recharge **uniquement** les props dépendantes
 * de `$fiscalYear` côté controller : `fiscalYearBreakdown` et `fiscalYear`
 * lui-même. Le reste du payload (`vehicle`, `vehicleBilling`, ...) reste
 * intact pour préserver l'indépendance avec les autres onglets.
 */

import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import type { Ref } from 'vue';

export function useVehicleFiscalSelectedYear(
    initialYear: number,
): {
    selectedYear: Ref<number>;
    selectYear: (year: number) => void;
    loading: Ref<boolean>;
} {
    const selectedYear = ref<number>(initialYear);
    const loading = ref<boolean>(false);

    function selectYear(year: number): void {
        if (year === selectedYear.value || loading.value) {
            return;
        }

        selectedYear.value = year;
        loading.value = true;

        // Préserve les autres query params existants (notamment `tab`,
        // `billingYear`...) en construisant l'URL à partir de l'URL
        // courante. router.get(pathname, params) écraserait tout.
        const url = new URL(window.location.href);
        url.searchParams.set('fiscalYear', String(year));

        router.get(
            url.pathname + url.search,
            {},
            {
                only: ['fiscalYearBreakdown', 'fiscalYear'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onFinish: () => {
                    loading.value = false;
                },
            },
        );
    }

    return { selectedYear, selectYear, loading };
}
