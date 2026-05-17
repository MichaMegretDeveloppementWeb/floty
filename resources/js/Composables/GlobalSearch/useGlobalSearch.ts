import { ref, watch } from 'vue';
import type { Ref } from 'vue';
import { search as searchRoute } from '@/routes/user';

/**
 * Couche HTTP de la palette de recherche globale ⌘K (V1.1).
 *
 * Watcher debouncé (200 ms) sur le `query` saisi dans la palette. À
 * chaque changement, annule la requête précédente (AbortController)
 * puis lance une nouvelle requête `GET /app/search?q=<query>`.
 *
 * Comportements ·
 *  - Query < 2 caractères → réinitialise les résultats à `null` (état
 *    « vide », la palette affichera les recents localStorage)
 *  - Requête annulée (typing rapide) → silencieuse, pas de toast
 *  - Erreur HTTP / réseau → `result` reste à sa valeur précédente,
 *    `loading` repasse à false (échec silencieux · le user voit juste
 *    les résultats anciens, pas de spam de toasts)
 *
 * Backend · {@see GlobalSearchService::searchAll}
 * Route · `user.search` (Wayfinder)
 */
const DEBOUNCE_MS = 200;
const MIN_LENGTH = 2;

export type GlobalSearchResult = App.Data.User.Search.GlobalSearchResultData;

export type UseGlobalSearchReturn = {
    result: Ref<GlobalSearchResult | null>;
    loading: Ref<boolean>;
    reset: () => void;
};

export function useGlobalSearch(query: Ref<string>): UseGlobalSearchReturn {
    const result = ref<GlobalSearchResult | null>(null);
    const loading = ref<boolean>(false);
    let abortController: AbortController | null = null;
    let debounceHandle: number | null = null;

    const cancelPending = (): void => {
        if (debounceHandle !== null) {
            window.clearTimeout(debounceHandle);
            debounceHandle = null;
        }

        if (abortController !== null) {
            abortController.abort();
            abortController = null;
        }
    };

    const reset = (): void => {
        cancelPending();
        result.value = null;
        loading.value = false;
    };

    watch(query, (raw) => {
        cancelPending();

        const trimmed = raw.trim();

        if (trimmed.length < MIN_LENGTH) {
            result.value = null;
            loading.value = false;

            return;
        }

        debounceHandle = window.setTimeout(async () => {
            loading.value = true;
            const controller = new AbortController();
            abortController = controller;

            try {
                const response = await fetch(
                    searchRoute.url({ query: { q: trimmed } }),
                    {
                        credentials: 'include',
                        signal: controller.signal,
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    },
                );

                if (!response.ok) {
                    return;
                }

                result.value = (await response.json()) as GlobalSearchResult;
            } catch (e) {
                if (e instanceof DOMException && e.name === 'AbortError') {
                    return;
                }
            } finally {
                if (abortController === controller) {
                    abortController = null;
                }

                loading.value = false;
            }
        }, DEBOUNCE_MS);
    });

    return { result, loading, reset };
}
