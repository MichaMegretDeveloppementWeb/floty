/**
 * Composable générique de chargement année par année avec cache client
 * (chantier η Phase 2 — refonte onglets fiche véhicule).
 *
 * **Pattern** : une carte/section porte des données paramétrées par une
 * année (Timeline + Breakdown sur véhicule, Coût plein détaillé, etc.).
 * L'année initiale est passée dans le payload Inertia normal de la page.
 * Quand l'utilisateur change l'année dans le sélecteur de la carte :
 *
 *   1. On regarde si l'année est déjà en cache local (`Map<year, T>`).
 *   2. Si oui → affichage immédiat, zéro round-trip.
 *   3. Sinon → fetch JSON ciblé vers `fetchFn(year)`, stockage cache,
 *      affichage. État `isLoading: true` pendant l'attente.
 *
 * Évite le pré-calcul backend de toutes les années (économie ressources)
 * tout en gardant l'UX d'un sélecteur local instantané pour les années
 * déjà visitées.
 *
 * **Pas de sync URL** : les sélecteurs sont locaux à leur composant et
 * indépendants entre eux (ex. carte Utilisation vs onglet Fiscalité ont
 * chacun leur propre cache et leur propre année courante). F5 reset le
 * cache et retombe sur l'année initiale.
 */

import { computed, ref } from 'vue';
import type { Ref, WritableComputedRef } from 'vue';

export type UseYearLazyReturn<T> = {
    /** Année actuellement affichée. */
    year: Ref<number>;
    /** Wrapper `v-model` qui passe par `selectYear()` côté setter. */
    yearModel: WritableComputedRef<number>;
    /** Données pour `year.value` (ou `null` durant un fetch initial). */
    data: Ref<T | null>;
    /** `true` pendant un fetch en cours. */
    isLoading: Ref<boolean>;
    /** Erreur du dernier fetch (si applicable). */
    error: Ref<string | null>;
    /** Demande explicite de bascule sur une année. */
    selectYear: (year: number) => Promise<void>;
};

export function useYearLazy<T>(
    initialYear: number,
    initialData: T,
    fetchFn: (year: number) => Promise<T>,
): UseYearLazyReturn<T> {
    const year = ref<number>(initialYear);
    const cache = new Map<number, T>([[initialYear, initialData]]);
    const data = ref<T | null>(initialData) as Ref<T | null>;
    const isLoading = ref<boolean>(false);
    const error = ref<string | null>(null);

    async function selectYear(target: number): Promise<void> {
        if (target === year.value) {
            return;
        }

        const cached = cache.get(target);

        if (cached !== undefined) {
            year.value = target;
            data.value = cached;

            return;
        }

        isLoading.value = true;
        error.value = null;

        try {
            const fetched = await fetchFn(target);
            cache.set(target, fetched);
            year.value = target;
            data.value = fetched;
        } catch (e) {
            error.value = e instanceof Error ? e.message : 'Erreur inconnue';
        } finally {
            isLoading.value = false;
        }
    }

    const yearModel = computed<number>({
        get: () => year.value,
        set: (value: number) => {
            void selectYear(value);
        },
    });

    return { year, yearModel, data, isLoading, error, selectYear };
}
