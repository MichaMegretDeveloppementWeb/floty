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
 * véhicule), `fiscalActiveDeclaration` (déclaration active de
 * l'année) et `pendingDeclarations` (alerte « À finaliser »). Ne
 * recharge pas `company`, `contracts`, ni les autres props
 * indépendantes du year fiscal.
 */

import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import type { Ref } from 'vue';

export function useCompanyFiscalSelectedYear(
    initialYear: number,
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

        // Préserve les autres query params existants (notamment `tab`,
        // `periodStart/End`, `page`...) en construisant l'URL à partir de
        // l'URL courante. router.get(pathname, params) écraserait tout.
        const url = new URL(window.location.href);
        url.searchParams.set('fiscalYear', String(year));

        router.get(
            url.pathname + url.search,
            {},
            {
                only: ['companyFiscal', 'fiscalActiveDeclaration', 'pendingDeclarations'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }

    return { selectedYear, selectYear };
}
