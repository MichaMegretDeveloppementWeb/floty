import { usePage } from '@inertiajs/vue3';
import { computed, type ComputedRef } from 'vue';

/**
 * Source de vérité unique pour l'année fiscale côté front.
 *
 * Lit `usePage().props.fiscal.currentYear` exposé par `HandleInertiaRequests`,
 * lui-même alimenté par `config('floty.fiscal.current_year')` côté Laravel.
 *
 * Aucune page, composant ou composable ne doit lire l'année autrement
 * (ni `new Date().getFullYear()`, ni prop locale `fiscalYear`, ni valeur
 * hardcodée). Cette règle garantit la cohérence visuelle entre toutes
 * les vues tant qu'une seule année est supportée.
 */
export type UseFiscalYearReturn = {
    /** Année fiscale courante (ex. 2024). Réactif aux shared props. */
    currentYear: ComputedRef<number>;
    /** Liste des années disponibles dans la configuration. */
    availableYears: ComputedRef<number[]>;
    /** Vrai si une seule année est disponible (sélecteur figé). */
    isLocked: ComputedRef<boolean>;
};

export function useFiscalYear(): UseFiscalYearReturn {
    const page = usePage();

    const currentYear = computed<number>(
        () => page.props.fiscal.currentYear,
    );
    const availableYears = computed<number[]>(
        () => page.props.fiscal.availableYears,
    );
    const isLocked = computed<boolean>(
        () => availableYears.value.length <= 1,
    );

    return { currentYear, availableYears, isLocked };
}
