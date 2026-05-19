import { ref, watch } from 'vue';
import type { Ref } from 'vue';
import { search as searchRoute } from '@/routes/user';

/**
 * HTTP layer for the ⌘K global search palette.
 *
 * Debounced watcher (200 ms) on the palette `query`. Each change aborts the previous request
 * via AbortController then issues a new `GET /app/search?q=<query>`.
 *
 * Behaviours:
 *  - Query < 2 chars: results reset to `null` (empty state; palette shows recents from localStorage)
 *  - Cancelled request (fast typing): silent, no toast
 *  - HTTP/network error: `result` keeps its previous value, `loading` flips back to false
 *    (silent failure: user keeps the old results, no toast spam)
 *
 * Backend: {@see GlobalSearchService::searchAll}. Route: `user.search` (Wayfinder).
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
