/**
 * Sync de l'année sélectionnée sur l'onglet Fiscalité de la fiche
 * Company Show (chantier N.2). Pattern strict ADR-0020 D3 : sélecteur
 * **local et indépendant**, jamais lié à un sélecteur global.
 *
 * Préfixe URL `?fiscalYear=` pour ne pas collide avec :
 *  - `?year=`           sélecteur Activité (Vue d'ensemble)
 *  - `?periodStart/End` filtre période Contrats
 *  - `?tab=`            onglet actif
 *  - les params de pagination/tri standards (`page`, `sortKey`, …)
 *
 * Le partial reload Inertia recharge **toutes les props dépendantes
 * de `$fiscalYear`** côté controller : `companyFiscal` (breakdown
 * véhicule), `declarationLifecycle` (état complet du cycle de vie
 * pour l'année, Phase 11 D5.8) et `pendingDeclarations` (alerte
 * « À finaliser »). Ne recharge pas `company`, `contracts`, ni les
 * autres props indépendantes du year fiscal.
 */

import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import type { Ref } from 'vue';

export function useCompanyFiscalSelectedYear(
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
        // `periodStart/End`, `page`...) en construisant l'URL à partir de
        // l'URL courante. router.get(pathname, params) écraserait tout.
        const url = new URL(window.location.href);
        url.searchParams.set('fiscalYear', String(year));

        router.get(
            url.pathname + url.search,
            {},
            {
                only: ['companyFiscal', 'declarationLifecycle', 'pendingDeclarations'],
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
