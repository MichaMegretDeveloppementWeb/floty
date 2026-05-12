/**
 * Composable générique de chargement année par année avec cache client
 * (chantier η Phase 2 · refonte onglets fiche véhicule).
 *
 * **Pattern** : une carte/section porte des données paramétrées par une
 * année (Timeline + Breakdown sur véhicule, Taxe pleine détaillé, etc.).
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
 * **Sync URL (opt-in)** : si `urlParam` est fourni, l'année courante est
 * synchronisée avec le query string correspondant via `history.replaceState`
 * (pas de navigation, pas de reload Inertia). Au mount, si le param URL
 * est présent et valide, l'année initiale est ajustée et un `selectYear()`
 * est déclenché en arrière-plan pour aligner les données. Sinon, comportement
 * historique : sélecteurs locaux, F5 retombe sur l'année initiale.
 */

import { computed, onMounted, ref } from 'vue';
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
    /**
     * Vide le cache et refetch l'année actuelle. Pour `initialYear`,
     * on peut passer `freshInitial` qui remplace directement les données
     * sans round-trip serveur (typique : un parent vient de recevoir un
     * nouveau payload Inertia post-CRUD et veut propager les nouvelles
     * stats à la carte sans bloquer l'UI).
     */
    invalidate: (freshInitial?: T) => Promise<void>;
};

export function useYearLazy<T>(
    initialYear: number,
    initialData: T,
    fetchFn: (year: number) => Promise<T>,
    options: { urlParam?: string } = {},
): UseYearLazyReturn<T> {
    const year = ref<number>(initialYear);
    const cache = new Map<number, T>([[initialYear, initialData]]);
    const data = ref<T | null>(initialData) as Ref<T | null>;
    const isLoading = ref<boolean>(false);
    const error = ref<string | null>(null);

    function syncUrl(target: number): void {
        if (options.urlParam === undefined || typeof window === 'undefined') {
            return;
        }

        const url = new URL(window.location.href);

        if (target === initialYear) {
            url.searchParams.delete(options.urlParam);
        } else {
            url.searchParams.set(options.urlParam, String(target));
        }

        window.history.replaceState(window.history.state, '', url.toString());
    }

    async function selectYear(target: number): Promise<void> {
        if (target === year.value) {
            return;
        }

        const cached = cache.get(target);

        if (cached !== undefined) {
            year.value = target;
            data.value = cached;
            syncUrl(target);

            return;
        }

        isLoading.value = true;
        error.value = null;

        try {
            const fetched = await fetchFn(target);
            cache.set(target, fetched);
            year.value = target;
            data.value = fetched;
            syncUrl(target);
        } catch (e) {
            error.value = e instanceof Error ? e.message : 'Erreur inconnue';
        } finally {
            isLoading.value = false;
        }
    }

    // Hydratation depuis l'URL au mount : si le param est présent et
    // diffère de l'année initiale, on aligne via selectYear(). Le fetch
    // est asynchrone, l'UI affiche d'abord initialData puis se met à jour.
    if (options.urlParam !== undefined) {
        onMounted(() => {
            if (typeof window === 'undefined') {
                return;
            }

            const raw = new URL(window.location.href).searchParams.get(
                options.urlParam as string,
            );

            if (raw === null) {
                return;
            }

            const parsed = Number.parseInt(raw, 10);

            if (Number.isNaN(parsed) || parsed === initialYear) {
                return;
            }

            void selectYear(parsed);
        });
    }

    const yearModel = computed<number>({
        get: () => year.value,
        set: (value: number) => {
            void selectYear(value);
        },
    });

    async function invalidate(freshInitial?: T): Promise<void> {
        cache.clear();

        if (freshInitial !== undefined) {
            cache.set(initialYear, freshInitial);

            if (year.value === initialYear) {
                data.value = freshInitial;

                return;
            }
        }

        // Refetch l'année actuelle (peut être ≠ initialYear si l'utilisateur
        // a bascule sur une autre année avant le CRUD parent).
        const target = year.value;

        isLoading.value = true;
        error.value = null;

        try {
            const fetched = await fetchFn(target);
            cache.set(target, fetched);
            data.value = fetched;
        } catch (e) {
            error.value = e instanceof Error ? e.message : 'Erreur inconnue';
        } finally {
            isLoading.value = false;
        }
    }

    return { year, yearModel, data, isLoading, error, selectYear, invalidate };
}
