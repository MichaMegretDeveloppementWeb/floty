/**
 * Generic composable: orchestrates `{filters, sort, page, perPage, search}` state for a
 * server-side Index table and triggers an Inertia v3 partial reload on each interaction (ADR-0020).
 *
 * Decisions:
 * - Filter + sort + pagination 100% server-side (no JS filtering).
 * - 300 ms debounce on `search` only. All other interactions (sort, page, perPage, filters) reload immediately.
 * - Anti-race: any non-debounced change cancels the pending `search` timer and reloads with the full state
 *   (including the last typed value).
 * - URL synced automatically by Inertia v3 via the `data` passed to `router.reload`.
 * - Initial state read from props (echoed by the backend), never from `window.location` at mount, to avoid
 *   hydration mismatch.
 * - Page auto-resets to 1 when search / filters / perPage change (not on sort).
 */

import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import type { ComputedRef, Ref } from 'vue';

export type SortDirection = 'asc' | 'desc';

export type SortState = {
    key: string | null;
    direction: SortDirection;
};

export type ServerTableStateOptions<F extends Record<string, unknown>> = {
    /** Inertia prop keys to reload during the partial reload. */
    only: readonly string[];
    /** Initial page (from props.query.page on the backend). */
    initialPage: number;
    /** Initial items-per-page. */
    initialPerPage: number;
    /** Initial search (empty string if backend returned null). */
    initialSearch: string;
    /** Initial sort. */
    initialSortKey: string | null;
    initialSortDirection: SortDirection;
    /**
     * "All neutral" filters: target state of `clearFilters`. Must represent total absence of filtering
     * (typically `null` for each key).
     */
    defaultFilters: F;
    /**
     * Mount-time filters (from props.query). Defaults to `defaultFilters`. Allows arriving via deep-link
     * with filters pre-applied.
     */
    initialFilters?: F;
    /**
     * Serialises domain filters to key/value pairs for `router.reload`'s `data`.
     * Return `null` to omit a key from the URL. An array value is serialised as
     * repeated `key[]=` params (multi-value filters); an empty array is omitted.
     */
    serializeFilters: (filters: F) => Record<string, string | number | null | string[]>;
    /** Search debounce in ms (default 300). */
    debounceMs?: number;
};

export type ServerTableState<F extends Record<string, unknown>> = {
    filters: Ref<F>;
    search: Ref<string>;
    sort: Ref<SortState>;
    page: Ref<number>;
    perPage: Ref<number>;
    isReloading: Ref<boolean>;
    activeSortKey: ComputedRef<string | null>;
    activeSortDirection: ComputedRef<SortDirection>;
    setFilter: <K extends keyof F>(key: K, value: F[K]) => void;
    /**
     * Updates several filters in a single reload. Use when a widget mutates 2+ logically linked
     * filters (e.g. DateRangePicker → periodStart + periodEnd): consecutive `setFilter` would fire
     * 2 requests, the first one partial (race) returning an inconsistent state.
     */
    patchFilters: (patch: Partial<F>) => void;
    setSort: (key: string) => void;
    setPage: (page: number) => void;
    setPerPage: (perPage: number) => void;
    setSearch: (search: string) => void;
    clearFilters: () => void;
};

const DEFAULT_DEBOUNCE_MS = 300;
const FACTORY_DEFAULT_PAGE = 1;
const FACTORY_DEFAULT_PER_PAGE = 20;
const FACTORY_DEFAULT_SORT_DIRECTION: SortDirection = 'asc';

export function useServerTableState<F extends Record<string, unknown>>(
    opts: ServerTableStateOptions<F>,
): ServerTableState<F> {
    const filters = ref<F>({
        ...(opts.initialFilters ?? opts.defaultFilters),
    }) as Ref<F>;
    const search = ref<string>(opts.initialSearch);
    const sort = ref<SortState>({
        key: opts.initialSortKey,
        direction: opts.initialSortDirection,
    });
    const page = ref<number>(opts.initialPage);
    const perPage = ref<number>(opts.initialPerPage);
    const isReloading = ref<boolean>(false);

    const debounceMs = opts.debounceMs ?? DEFAULT_DEBOUNCE_MS;
    let searchTimer: ReturnType<typeof setTimeout> | null = null;
    let pendingCancel: (() => void) | null = null;

    function buildQueryData(): Record<string, string | number | null | string[]> {
        const data: Record<string, string | number | null | string[]> = {
            page: page.value !== FACTORY_DEFAULT_PAGE ? page.value : null,
            perPage:
                perPage.value !== FACTORY_DEFAULT_PER_PAGE ? perPage.value : null,
            search: search.value !== '' ? search.value : null,
            sortKey: sort.value.key,
            sortDirection:
                sort.value.key !== null
                    && sort.value.direction !== FACTORY_DEFAULT_SORT_DIRECTION
                    ? sort.value.direction
                    : null,
            ...opts.serializeFilters(filters.value),
        };

        return data;
    }

    function reloadNow(): void {
        if (searchTimer !== null) {
            clearTimeout(searchTimer);
            searchTimer = null;
        }

        // Cancels the previous request if still in flight.
        // Anti-race: guarantees the LAST request wins regardless of response order.
        // Without this, two reloads fired back-to-back (e.g. DateRangePicker emitting on each input change)
        // could see the first response overwrite the second and display an inconsistent state.
        if (pendingCancel !== null) {
            pendingCancel();
            pendingCancel = null;
        }

        // `router.get(url, data, options)` is the documented pattern for partial reload WITH URL update.
        // `router.reload({ data })` does not update the browser URL, so the page does not refresh
        // correctly (sort/filter become invisible). `replace: true` avoids polluting history on each keystroke.
        const params = buildQueryData();

        // URL strategy: MERGE with existing query params instead of replacing everything.
        // A param outside the table (e.g. `?tab=contracts` set by useCompanyTabs, or any future global
        // selector) survives each table interaction. Without this, every consuming page would have to
        // inject its params into `serializeFilters` as a workaround.
        const url = new URL(window.location.href);

        // 1. Strip URL keys managed by the composable (standard table params + custom filter keys),
        //    so a filter that becomes null actually disappears from the URL instead of persisting.
        const baseKeys = ['page', 'perPage', 'search', 'sortKey', 'sortDirection'];
        const managedKeys = new Set<string>(baseKeys);

        for (const [key, value] of Object.entries(params)) {
            if (baseKeys.includes(key)) {
                continue;
            }

            // Array filters live under `key[]` in the URL; scalars under `key`.
            managedKeys.add(Array.isArray(value) ? `${key}[]` : key);
        }

        for (const key of managedKeys) {
            url.searchParams.delete(key);
        }

        // 2. Re-inject current values (filtering out null / empty arrays to keep
        //    the URL clean). Arrays become repeated `key[]=` params.
        for (const [key, value] of Object.entries(params)) {
            if (value === null) {
                continue;
            }

            if (Array.isArray(value)) {
                for (const item of value) {
                    url.searchParams.append(`${key}[]`, String(item));
                }

                continue;
            }

            url.searchParams.set(key, String(value));
        }

        router.get(
            url.pathname + url.search,
            {},
            {
                only: [...opts.only],
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onCancelToken: (token) => {
                    pendingCancel = token.cancel;
                },
                onStart: () => {
                    isReloading.value = true;
                },
                onFinish: () => {
                    isReloading.value = false;
                    pendingCancel = null;
                },
            },
        );
    }

    function reloadDebounced(): void {
        if (searchTimer !== null) {
            clearTimeout(searchTimer);
        }

        searchTimer = setTimeout(reloadNow, debounceMs);
    }

    function setFilter<K extends keyof F>(key: K, value: F[K]): void {
        filters.value = { ...filters.value, [key]: value };
        page.value = FACTORY_DEFAULT_PAGE;
        reloadNow();
    }

    function patchFilters(patch: Partial<F>): void {
        filters.value = { ...filters.value, ...patch };
        page.value = FACTORY_DEFAULT_PAGE;
        reloadNow();
    }

    function setSort(key: string): void {
        if (sort.value.key !== key) {
            sort.value = { key, direction: 'asc' };
            reloadNow();

            return;
        }

        if (sort.value.direction === 'asc') {
            sort.value = { key, direction: 'desc' };
            reloadNow();

            return;
        }

        // 3rd click: off (back to backend default order)
        sort.value = { key: null, direction: FACTORY_DEFAULT_SORT_DIRECTION };
        reloadNow();
    }

    function setPage(newPage: number): void {
        if (newPage === page.value) {
            return;
        }

        page.value = newPage;
        reloadNow();
    }

    function setPerPage(newPerPage: number): void {
        if (newPerPage === perPage.value) {
            return;
        }

        perPage.value = newPerPage;
        page.value = FACTORY_DEFAULT_PAGE;
        reloadNow();
    }

    function setSearch(newSearch: string): void {
        search.value = newSearch;
        page.value = FACTORY_DEFAULT_PAGE;
    }

    function clearFilters(): void {
        filters.value = { ...opts.defaultFilters };
        search.value = '';
        page.value = FACTORY_DEFAULT_PAGE;
        reloadNow();
    }

    // Watch search: triggers a debounced reload on each mutation.
    // The other refs are mutated by setters calling `reloadNow()` directly (no double-reload).
    watch(search, () => {
        reloadDebounced();
    });

    const activeSortKey = computed<string | null>(() => sort.value.key);
    const activeSortDirection = computed<SortDirection>(
        () => sort.value.direction,
    );

    return {
        filters,
        search,
        sort,
        page,
        perPage,
        isReloading,
        activeSortKey,
        activeSortDirection,
        setFilter,
        patchFilters,
        setSort,
        setPage,
        setPerPage,
        setSearch,
        clearFilters,
    };
}
