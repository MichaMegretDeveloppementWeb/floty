import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import { daysInYear as daysInYearOf } from '@/Utils/date/daysInYear';

/**
 * Source de vérité unique pour l'année fiscale côté front.
 *
 * Lit `usePage().props.fiscal.currentYear` exposé par
 * `HandleInertiaRequests`, lui-même alimenté par
 * `App\Fiscal\Resolver\FiscalYearResolver` (session utilisateur,
 * fallback `config('floty.fiscal.available_years')[0]`).
 *
 * Aucune page, composant ou composable ne doit lire l'année autrement
 * (ni `new Date().getFullYear()`, ni prop locale `fiscalYear`, ni valeur
 * hardcodée). Cette règle garantit la cohérence visuelle entre toutes
 * les vues tant qu'une seule année est supportée.
 *
 * Expose aussi `daysInYear` (365/366) calculé à partir de l'année
 * courante — à consommer dans tous les prorata fiscaux côté UI pour
 * éviter les `/ 366` hardcodés.
 */
export type UseFiscalYearReturn = {
    /** Année fiscale courante (ex. 2024). Réactif aux shared props. */
    currentYear: ComputedRef<number>;
    /** Liste des années disponibles dans la configuration. */
    availableYears: ComputedRef<number[]>;
    /** Vrai si une seule année est disponible (sélecteur figé). */
    isLocked: ComputedRef<boolean>;
    /** Jours dans l'année courante (365 ou 366) — réactif. */
    daysInYear: ComputedRef<365 | 366>;
};

export function useFiscalYear(): UseFiscalYearReturn {
    const page = usePage();

    const currentYear = computed<number>(() => page.props.fiscal.currentYear);
    const availableYears = computed<number[]>(
        () => page.props.fiscal.availableYears,
    );
    const isLocked = computed<boolean>(() => availableYears.value.length <= 1);
    const daysInYear = computed<365 | 366>(() =>
        daysInYearOf(currentYear.value),
    );

    return { currentYear, availableYears, isLocked, daysInYear };
}
