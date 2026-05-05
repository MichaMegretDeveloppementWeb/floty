import { router } from '@inertiajs/vue3';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import { useServerTableState } from '@/Composables/Shared/useServerTableState';

type FakeFilters = {
    companyId: number | null;
    type: 'lcd' | 'lld' | null;
};

function createState(
    overrides: Partial<Parameters<typeof useServerTableState<FakeFilters>>[0]> = {},
) {
    return useServerTableState<FakeFilters>({
        only: ['drivers', 'query'],
        initialPage: 1,
        initialPerPage: 20,
        initialSearch: '',
        initialSortKey: null,
        initialSortDirection: 'asc',
        defaultFilters: { companyId: null, type: null },
        serializeFilters: (f) => ({
            companyId: f.companyId,
            type: f.type,
        }),
        ...overrides,
    });
}

/**
 * Depuis le refactor « préservation query params » (chantier J.0), les
 * paramètres de la table sont injectés dans l'URL passée en 1er argument
 * de `router.get(url, {}, options)`, plus dans le 2e argument `data`.
 * Helper pour récupérer l'URL en `URL` parsable.
 */
function urlOfCall(callIndex = 0): URL {
    const [urlString] = vi.mocked(router.get).mock.calls[callIndex]!;

    return new URL(urlString as string, 'http://localhost');
}

function paramsOfCall(callIndex = 0): Record<string, string> {
    const url = urlOfCall(callIndex);
    const out: Record<string, string> = {};

    for (const [key, value] of url.searchParams.entries()) {
        out[key] = value;
    }

    return out;
}

describe('useServerTableState', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        vi.mocked(router.get).mockClear();
        // Reset URL pour garantir un état déterministe entre tests
        window.history.replaceState({}, '', '/');
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('initialise les refs depuis les options sans déclencher de reload', () => {
        const state = createState({
            initialPage: 3,
            initialPerPage: 50,
            initialSearch: 'foo',
            initialSortKey: 'fullName',
            initialSortDirection: 'desc',
            initialFilters: { companyId: 42, type: 'lcd' },
        });

        expect(state.page.value).toBe(3);
        expect(state.perPage.value).toBe(50);
        expect(state.search.value).toBe('foo');
        expect(state.sort.value).toEqual({ key: 'fullName', direction: 'desc' });
        expect(state.filters.value).toEqual({ companyId: 42, type: 'lcd' });
        expect(router.get).not.toHaveBeenCalled();
    });

    it('debounce setSearch — pas de reload avant 300ms', async () => {
        const state = createState();
        state.setSearch('foo');
        await nextTick();

        vi.advanceTimersByTime(299);
        expect(router.get).not.toHaveBeenCalled();

        vi.advanceTimersByTime(1);
        expect(router.get).toHaveBeenCalledTimes(1);
    });

    it("debounce setSearch — frappe rapide ne déclenche qu'un reload", async () => {
        const state = createState();

        state.setSearch('f');
        await nextTick();
        vi.advanceTimersByTime(100);

        state.setSearch('fo');
        await nextTick();
        vi.advanceTimersByTime(100);

        state.setSearch('foo');
        await nextTick();
        vi.advanceTimersByTime(300);

        expect(router.get).toHaveBeenCalledTimes(1);
        expect(paramsOfCall().search).toBe('foo');
    });

    it('setSort déclenche un reload immédiat et annule le pending search timer', async () => {
        const state = createState();

        state.setSearch('foo');
        await nextTick();
        vi.advanceTimersByTime(100);
        expect(router.get).not.toHaveBeenCalled();

        state.setSort('fullName');
        expect(router.get).toHaveBeenCalledTimes(1);
        const params = paramsOfCall();
        expect(params.search).toBe('foo');
        expect(params.sortKey).toBe('fullName');

        // Le timer search annulé ne doit plus tirer
        vi.advanceTimersByTime(500);
        expect(router.get).toHaveBeenCalledTimes(1);
    });

    it('setSort cycle asc → desc → off', () => {
        const state = createState();

        state.setSort('fullName');
        expect(state.sort.value).toEqual({ key: 'fullName', direction: 'asc' });

        state.setSort('fullName');
        expect(state.sort.value).toEqual({ key: 'fullName', direction: 'desc' });

        state.setSort('fullName');
        expect(state.sort.value).toEqual({ key: null, direction: 'asc' });
    });

    it('setSort sur autre colonne réinitialise direction à asc', () => {
        const state = createState();

        state.setSort('fullName');
        state.setSort('fullName'); // desc maintenant
        expect(state.sort.value.direction).toBe('desc');

        state.setSort('createdAt');
        expect(state.sort.value).toEqual({ key: 'createdAt', direction: 'asc' });
    });

    it('setPage déclenche un reload immédiat', () => {
        const state = createState();
        state.setPage(3);

        expect(state.page.value).toBe(3);
        expect(router.get).toHaveBeenCalledTimes(1);
        expect(paramsOfCall().page).toBe('3');
    });

    it('setPage no-op si même page', () => {
        const state = createState({ initialPage: 2 });
        state.setPage(2);

        expect(router.get).not.toHaveBeenCalled();
    });

    it('setPerPage réinitialise page à 1 et reload', () => {
        const state = createState({ initialPage: 5 });
        state.setPerPage(50);

        expect(state.perPage.value).toBe(50);
        expect(state.page.value).toBe(1);
        expect(router.get).toHaveBeenCalledTimes(1);
        expect(paramsOfCall().perPage).toBe('50');
    });

    it('setFilter réinitialise page à 1 et reload', () => {
        const state = createState({ initialPage: 5 });
        state.setFilter('companyId', 42);

        expect(state.filters.value.companyId).toBe(42);
        expect(state.page.value).toBe(1);
        expect(router.get).toHaveBeenCalledTimes(1);
        expect(paramsOfCall().companyId).toBe('42');
    });

    it('patchFilters met à jour plusieurs filtres en un seul reload', () => {
        const state = createState({ initialPage: 4 });
        state.patchFilters({ companyId: 42, type: 'lcd' });

        expect(state.filters.value).toEqual({ companyId: 42, type: 'lcd' });
        expect(state.page.value).toBe(1);
        expect(router.get).toHaveBeenCalledTimes(1);

        const params = paramsOfCall();
        expect(params.companyId).toBe('42');
        expect(params.type).toBe('lcd');
    });

    it('patchFilters préserve les filtres non passés dans le patch', () => {
        const state = createState({
            initialFilters: { companyId: 7, type: 'lld' },
        });
        state.patchFilters({ companyId: 99 });

        expect(state.filters.value).toEqual({ companyId: 99, type: 'lld' });
    });

    it('clearFilters réinitialise filters + search + page et reload une fois', () => {
        const state = createState({
            initialPage: 3,
            initialSearch: 'foo',
            initialFilters: { companyId: 42, type: 'lcd' },
        });

        state.clearFilters();

        expect(state.filters.value).toEqual({ companyId: null, type: null });
        expect(state.search.value).toBe('');
        expect(state.page.value).toBe(1);
        expect(router.get).toHaveBeenCalledTimes(1);
    });

    it('buildQueryData omet les valeurs nulles (URL propre)', () => {
        const state = createState();
        state.setSort('fullName'); // sortKey set, direction asc (default → omit)

        const params = paramsOfCall();

        // Les valeurs par défaut + null sont absentes de la query string
        expect(params).not.toHaveProperty('page');
        expect(params).not.toHaveProperty('perPage');
        expect(params).not.toHaveProperty('search');
        expect(params).not.toHaveProperty('sortDirection');
        expect(params).not.toHaveProperty('companyId');
        expect(params).not.toHaveProperty('type');
        expect(params.sortKey).toBe('fullName');
    });

    it('buildQueryData expose sortDirection desc', () => {
        const state = createState();
        state.setSort('fullName');
        state.setSort('fullName'); // desc

        expect(paramsOfCall(1).sortDirection).toBe('desc');
    });

    it('isReloading toggle via callbacks onStart / onFinish', () => {
        const state = createState();
        state.setPage(2);

        const [, , options] = vi.mocked(router.get).mock.calls[0]!;
        expect(state.isReloading.value).toBe(false);

        options?.onStart?.({} as never);
        expect(state.isReloading.value).toBe(true);

        options?.onFinish?.({} as never);
        expect(state.isReloading.value).toBe(false);
    });

    it('appel router.get avec url contenant les params + options correctes', () => {
        const state = createState({ only: ['contracts', 'meta'] });
        state.setPage(2);

        // router.get(url, {}, options) — paramètres dans l'URL, pas dans le 2e arg
        expect(router.get).toHaveBeenCalledWith(
            expect.stringContaining('page=2'),
            {},
            expect.objectContaining({
                only: ['contracts', 'meta'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }),
        );
    });

    // ----------------------------------------------------------------
    // Préservation des query params hors-table (chantier J.0)
    // ----------------------------------------------------------------

    it('préserve un query param non géré par le composable (ex. ?tab=contracts)', () => {
        // Le user a `?tab=contracts` dans l'URL avant l'interaction (posé
        // par useCompanyTabs sur la fiche entreprise). Une interaction de
        // table ne doit PAS supprimer ce param.
        window.history.replaceState({}, '', '/?tab=contracts');

        const state = createState();
        state.setPage(3);

        const params = paramsOfCall();
        expect(params.tab).toBe('contracts');
        expect(params.page).toBe('3');
    });

    it('préserve plusieurs query params hors-table en parallèle', () => {
        window.history.replaceState({}, '', '/?tab=fiscal&fiscalYear=2024');

        const state = createState();
        state.setSort('fullName');

        const params = paramsOfCall();
        expect(params.tab).toBe('fiscal');
        expect(params.fiscalYear).toBe('2024');
        expect(params.sortKey).toBe('fullName');
    });

    it('retire un filtre quand sa valeur passe à null (URL propre)', () => {
        // Le user arrive avec ?companyId=42 dans l'URL puis le clear via
        // setFilter(...null). L'URL doit perdre la clé companyId, mais
        // garder les autres params (ex. ?tab=foo).
        window.history.replaceState({}, '', '/?tab=foo&companyId=42');

        const state = createState({ initialFilters: { companyId: 42, type: null } });
        state.setFilter('companyId', null);

        const params = paramsOfCall();
        expect(params).not.toHaveProperty('companyId');
        expect(params.tab).toBe('foo');
    });
});
