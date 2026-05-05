/**
 * Sélecteur d'année local à une page (chantier J, ADR-0020).
 *
 * Pattern : chaque page consommatrice (Dashboard, Planning, FiscalRules,
 * Vehicles Index, Companies Index, Contracts Index) gère sa propre
 * année via `?year=YYYY` URL. Le composable encapsule la mécanique
 * de partial reload Inertia avec **préservation des autres query params**
 * (filtres, tri, pagination, etc.).
 *
 * Pour les Index qui utilisent `useServerTableState`, passer `year` au
 * `serializeFilters` plutôt que ce composable (cohérence avec le reste
 * des filtres). Pour les pages standalone (Dashboard, FiscalRules), ce
 * composable est l'API directe.
 */

import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import type { Ref } from 'vue';

export function useLocalYearSelector(
    initialYear: number,
    only: readonly string[],
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

        // Préserve les autres query params en construisant l'URL depuis
        // l'URL courante (ne pas utiliser router.get(pathname, params)
        // qui écrase tout).
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
            },
        );
    }

    return { selectedYear, selectYear };
}
