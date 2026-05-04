/**
 * Composable générique : orchestre l'état `{filters, sort, page, perPage,
 * search}` d'une table Index server-side et déclenche un partial reload
 * Inertia v3 à chaque interaction (cf. ADR-0020).
 *
 * **Décisions** :
 * - Filtre + tri + pagination 100 % côté serveur (pas de filtrage JS).
 * - Debounce 300ms uniquement sur `search`. Toutes les autres interactions
 *   (sort, page, perPage, filtres) déclenchent un reload immédiat.
 * - Anti-race : tout changement non-debouncé annule le pending timer
 *   `search` et reload avec l'état complet (incluant la dernière valeur
 *   tapée).
 * - URL synchronisée automatiquement par Inertia v3 via le `data` passé
 *   à `router.reload`.
 * - Initial state lu depuis les props (échoées par le backend), jamais
 *   depuis `window.location` au mount, pour éviter l'hydration mismatch.
 * - Reset automatique de la page à 1 quand search / filtres / perPage
 *   changent (mais pas quand sort change).
 *
 * Usage type :
 *   const tableState = useServerTableState<DriverFilters>({
 *     only: ['drivers', 'query'],
 *     initialPage: props.query.page,
 *     initialPerPage: props.query.perPage,
 *     initialSearch: props.query.search ?? '',
 *     initialSortKey: props.query.sortKey,
 *     initialSortDirection: props.query.sortDirection,
 *     initialFilters: { ... },
 *     serializeFilters: (f) => ({ ... }),
 *   });
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
    /** Clés de props Inertia à recharger lors du partial reload. */
    only: readonly string[];
    /** Page initiale (depuis props.query.page côté backend). */
    initialPage: number;
    /** Items par page initial. */
    initialPerPage: number;
    /** Search initial (string vide si backend a renvoyé null). */
    initialSearch: string;
    /** Sort initial. */
    initialSortKey: string | null;
    initialSortDirection: SortDirection;
    /**
     * Filtres "tout neutre" — état cible de `clearFilters`. Doit
     * représenter l'absence totale de filtre (typiquement `null` pour
     * chaque clé).
     */
    defaultFilters: F;
    /**
     * Filtres au mount (depuis props.query). Si omis, vaut
     * `defaultFilters`. Permet l'arrivée via deep-link avec filtres
     * pré-appliqués.
     */
    initialFilters?: F;
    /**
     * Sérialise les filtres spécifiques en paires clé/valeur pour le
     * `data` de `router.reload`. Retourner `null` pour omettre la clé
     * de l'URL.
     */
    serializeFilters: (filters: F) => Record<string, string | number | null>;
    /** Délai du debounce sur search en ms (défaut 300). */
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
     * Met à jour plusieurs filtres en un seul reload. À utiliser quand
     * un widget UI modifie 2+ filtres logiquement liés (ex. DateRangePicker
     * → periodStart + periodEnd) : `setFilter` consécutifs déclenchent 2
     * requests dont la 1ère est partielle (race) et peut renvoyer un état
     * incohérent (cf. bug filtre période Contracts, 2026-05).
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

    function buildQueryData(): Record<string, string | number | null> {
        const data: Record<string, string | number | null> = {
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

        // Annule la requête précédente si elle est encore en vol.
        // Anti-race : garantit que la DERNIÈRE requête gagne, peu importe
        // l'ordre d'arrivée des réponses. Sans ça, si 2 reloads sont
        // déclenchés coup sur coup (ex. DateRangePicker qui émet une
        // update par input modifié), la 1ère réponse peut écraser la 2ème
        // et afficher un état incohérent (cf. bug filtre période 2026-05).
        if (pendingCancel !== null) {
            pendingCancel();
            pendingCancel = null;
        }

        // Cf. inertia-navigation.md § 10 : `router.get(url, data, options)`
        // est le pattern documenté pour partial reload AVEC URL update.
        // `router.reload({ data })` ne met pas à jour l'URL côté navigateur
        // → la page ne s'actualise pas correctement (tri/filtre invisibles).
        // `replace: true` évite de polluer l'historique sur chaque keystroke.
        const params = buildQueryData();

        // Filtre les valeurs null pour garder l'URL propre.
        const cleanParams: Record<string, string | number> = {};

        for (const [key, value] of Object.entries(params)) {
            if (value !== null) {
                cleanParams[key] = value;
            }
        }

        router.get(window.location.pathname, cleanParams, {
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
        });
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

        // 3ᵉ clic : off (revient à l'ordre par défaut backend)
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

    // Watch search : déclenche un reload debouncé à chaque mutation.
    // Les autres refs sont mutées par les setters qui appellent
    // `reloadNow()` directement (donc pas de double-reload).
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
