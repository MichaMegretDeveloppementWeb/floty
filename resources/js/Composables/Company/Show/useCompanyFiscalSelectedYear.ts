/**
 * Sync de l'année sélectionnée sur l'onglet Fiscalité de la fiche
 * Company Show.
 *
 * **D5.10.U** · param URL unifié `?year=` partagé entre les onglets.
 * **D5.10.V** · le partial reload ne tire QUE les props de l'onglet
 * Fiscal courant. Les autres onglets year-dépendants (`billing`,
 * `contracts`) sont marqués stale via `markStale(...)` · ils seront
 * re-fetchés au prochain clic. Cohérent avec le chargement lazy +
 * cumulatif (cf. `useCompanyTabs`).
 *
 * `pendingDeclarations` / `pendingInvoices` ne dépendent pas de
 * `?year=` (cross-year) · ils ne sont pas rechargés.
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
        // D5.10.U · efface tout filtre custom Contracts d'un précédent
        // état · sinon l'utilisateur retomberait sur une plage de
        // l'ancien exercice en switchant sur l'onglet Contracts.
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
                    // Onglets year-dépendants invalidés · ils
                    // tireront leurs props au prochain `setTab(...)`.
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
