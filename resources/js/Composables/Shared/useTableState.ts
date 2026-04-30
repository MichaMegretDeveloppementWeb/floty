/**
 * Composable générique : tri + filtres pour les tables de listes
 * (Contrats, Flotte, Entreprises).
 *
 * **Décisions** (chantier D) :
 * - Filtrage et tri 100 % côté client (volumétrie V1 faible).
 * - État synchronisé avec l'URL via query params (liens partageables).
 * - Tri colonne unique (asc → desc → off au clic répété).
 *
 * Le composable expose :
 *   - `filters: Ref<F>` (état réactif des filtres)
 *   - `sort: Ref<{ key, direction }>`
 *   - `setSort(key)`, `clearFilters()`, `setFilter(key, value)`
 *   - `apply(items)`: filtre + trie un tableau d'items
 *   - `activeFiltersCount: ComputedRef<number>`
 */

import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import type { ComputedRef, Ref } from 'vue';

type SortDirection = 'asc' | 'desc';

type SortState<K extends string> = {
    key: K | null;
    direction: SortDirection;
};

export type TableStateOptions<T, F extends Record<string, unknown>, K extends string> = {
    /** Valeurs par défaut des filtres (état "tout neutre"). */
    defaultFilters: F;
    /**
     * Parse l'URL query string (`URLSearchParams`) et reconstruit le shape
     * F. Retourne `defaultFilters` pour les params absents/invalides.
     */
    parseFiltersFromUrl: (params: URLSearchParams) => F;
    /**
     * Sérialise le shape F en query params. Une valeur `null` indique de
     * retirer la clé de l'URL.
     */
    serializeFiltersToUrl: (filters: F) => Record<string, string | null>;
    /** Prédicat de filtrage appliqué item par item. */
    applyFilter: (item: T, filters: F) => boolean;
    /**
     * Comparateurs par colonne triable. Le résultat est multiplié par
     * `direction` (1 pour asc, -1 pour desc) dans `apply()`.
     */
    sortComparators: Record<K, (a: T, b: T) => number>;
    /**
     * Parse `?sortKey=…&sortDir=…` depuis l'URL. La sortKey doit être une
     * des clés de `sortComparators` ; sinon `null`.
     */
    sortKeys: readonly K[];
};

export type TableState<T, F extends Record<string, unknown>, K extends string> = {
    filters: Ref<F>;
    sort: Ref<SortState<K>>;
    setSort: (key: K) => void;
    setFilter: <Key extends keyof F>(key: Key, value: F[Key]) => void;
    clearFilters: () => void;
    activeFiltersCount: ComputedRef<number>;
    apply: (items: readonly T[]) => T[];
};

const DEBOUNCE_MS = 200;

export function useTableState<
    T,
    F extends Record<string, unknown>,
    K extends string,
>(opts: TableStateOptions<T, F, K>): TableState<T, F, K> {
    const initialParams = new URLSearchParams(
        typeof window !== 'undefined' ? window.location.search : '',
    );

    const filters = ref<F>(opts.parseFiltersFromUrl(initialParams)) as Ref<F>;

    const sortKeyParam = initialParams.get('sortKey');
    const sortDirParam = initialParams.get('sortDir');
    const sort = ref<SortState<K>>({
        key:
            sortKeyParam !== null && opts.sortKeys.includes(sortKeyParam as K)
                ? (sortKeyParam as K)
                : null,
        direction: sortDirParam === 'desc' ? 'desc' : 'asc',
    }) as Ref<SortState<K>>;

    let syncTimer: ReturnType<typeof setTimeout> | null = null;

    function syncToUrl(): void {
        if (typeof window === 'undefined') {
            return;
        }

        const params = new URLSearchParams(window.location.search);

        // Filtres
        const filterParams = opts.serializeFiltersToUrl(filters.value);

        for (const [key, value] of Object.entries(filterParams)) {
            if (value === null || value === '') {
                params.delete(key);
            } else {
                params.set(key, value);
            }
        }

        // Tri
        if (sort.value.key === null) {
            params.delete('sortKey');
            params.delete('sortDir');
        } else {
            params.set('sortKey', sort.value.key);
            params.set('sortDir', sort.value.direction);
        }

        const queryString = params.toString();
        const url = window.location.pathname + (queryString === '' ? '' : '?' + queryString);

        router.replace({
            url,
            preserveScroll: true,
            preserveState: true,
        });
    }

    function debouncedSync(): void {
        if (syncTimer !== null) {
            clearTimeout(syncTimer);
        }

        syncTimer = setTimeout(syncToUrl, DEBOUNCE_MS);
    }

    watch(filters, debouncedSync, { deep: true });
    watch(sort, debouncedSync, { deep: true });

    function setSort(key: K): void {
        if (sort.value.key !== key) {
            sort.value = { key, direction: 'asc' };

            return;
        }

        if (sort.value.direction === 'asc') {
            sort.value = { key, direction: 'desc' };

            return;
        }

        // 3ᵉ clic : off
        sort.value = { key: null, direction: 'asc' };
    }

    function setFilter<Key extends keyof F>(key: Key, value: F[Key]): void {
        filters.value = { ...filters.value, [key]: value };
    }

    function clearFilters(): void {
        filters.value = { ...opts.defaultFilters };
    }

    const activeFiltersCount = computed<number>(() => {
        let count = 0;

        for (const key of Object.keys(filters.value) as (keyof F)[]) {
            const current = filters.value[key];
            const defaultValue = opts.defaultFilters[key];

            if (
                current !== defaultValue
                && current !== null
                && current !== ''
                && current !== undefined
            ) {
                count += 1;
            }
        }

        return count;
    });

    function apply(items: readonly T[]): T[] {
        const filtered = items.filter((item) => opts.applyFilter(item, filters.value));

        if (sort.value.key === null) {
            return [...filtered];
        }

        const comparator = opts.sortComparators[sort.value.key];
        const direction = sort.value.direction === 'asc' ? 1 : -1;

        return [...filtered].sort((a, b) => comparator(a, b) * direction);
    }

    return {
        filters,
        sort,
        setSort,
        setFilter,
        clearFilters,
        activeFiltersCount,
        apply,
    };
}
