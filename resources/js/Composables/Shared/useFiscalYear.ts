import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';

/**
 * Liste des années configurées dans le moteur fiscal.
 *
 * **Chantier J (ADR-0020)** : la propriété `currentYear` (qui dépendait
 * de `session('fiscal.active_year')` via le `FiscalYearResolver` supprimé)
 * a été retirée. Chaque page consommatrice gère désormais sa propre
 * année via `?year=` URL + sélecteur local. Ce composable se limite à
 * exposer la liste des années configurées (utile pour peupler les
 * sélecteurs).
 *
 * Pour le calcul calendaire `daysInYear(year)`, importer la fonction
 * pure depuis `@/Utils/date/daysInYear` directement (passer l'année
 * locale en argument).
 */
export type UseFiscalYearReturn = {
    /** Liste des années configurées. Source : shared prop `fiscal.availableYears`. */
    availableYears: ComputedRef<number[]>;
    /** Vrai si une seule année est disponible (sélecteur figé visuellement). */
    isLocked: ComputedRef<boolean>;
};

export function useFiscalYear(): UseFiscalYearReturn {
    const page = usePage();

    const availableYears = computed<number[]>(
        () => page.props.fiscal.availableYears,
    );
    const isLocked = computed<boolean>(() => availableYears.value.length <= 1);

    return { availableYears, isLocked };
}
