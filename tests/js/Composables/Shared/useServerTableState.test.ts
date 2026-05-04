import { router } from '@inertiajs/vue3';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import { useServerTableState } from '@/Composables/Shared/useServerTableState';

type FakeFilters = {
    companyId: number | null;
    type: 'lcd' | 'lld' | null;
};

function createState(overrides: Partial<Parameters<typeof useServerTableState<FakeFilters>>[0]> = {}) {
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

describe('useServerTableState', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        vi.mocked(router.reload).mockClear();
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
        expect(router.reload).not.toHaveBeenCalled();
    });

    it('debounce setSearch — pas de reload avant 300ms', async () => {
        const state = createState();
        state.setSearch('foo');
        await nextTick();

        vi.advanceTimersByTime(299);
        expect(router.reload).not.toHaveBeenCalled();

        vi.advanceTimersByTime(1);
        expect(router.reload).toHaveBeenCalledTimes(1);
    });

    it('debounce setSearch — frappe rapide ne déclenche qu\'un reload', async () => {
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

        expect(router.reload).toHaveBeenCalledTimes(1);
        expect(router.reload).toHaveBeenLastCalledWith(
            expect.objectContaining({
                data: expect.objectContaining({ search: 'foo' }),
            }),
        );
    });

    it('setSort déclenche un reload immédiat et annule le pending search timer', async () => {
        const state = createState();

        state.setSearch('foo');
        await nextTick();
        vi.advanceTimersByTime(100);
        expect(router.reload).not.toHaveBeenCalled();

        state.setSort('fullName');
        expect(router.reload).toHaveBeenCalledTimes(1);
        expect(router.reload).toHaveBeenLastCalledWith(
            expect.objectContaining({
                data: expect.objectContaining({
                    search: 'foo',
                    sortKey: 'fullName',
                }),
            }),
        );

        // Le timer search annulé ne doit plus tirer
        vi.advanceTimersByTime(500);
        expect(router.reload).toHaveBeenCalledTimes(1);
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
        expect(router.reload).toHaveBeenCalledTimes(1);
    });

    it('setPage no-op si même page', () => {
        const state = createState({ initialPage: 2 });
        state.setPage(2);

        expect(router.reload).not.toHaveBeenCalled();
    });

    it('setPerPage réinitialise page à 1 et reload', () => {
        const state = createState({ initialPage: 5 });
        state.setPerPage(50);

        expect(state.perPage.value).toBe(50);
        expect(state.page.value).toBe(1);
        expect(router.reload).toHaveBeenCalledTimes(1);
    });

    it('setFilter réinitialise page à 1 et reload', () => {
        const state = createState({ initialPage: 5 });
        state.setFilter('companyId', 42);

        expect(state.filters.value.companyId).toBe(42);
        expect(state.page.value).toBe(1);
        expect(router.reload).toHaveBeenCalledTimes(1);
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
        expect(router.reload).toHaveBeenCalledTimes(1);
    });

    it('buildQueryData omet les valeurs par défaut', () => {
        const state = createState();
        state.setSort('fullName'); // sortKey set, direction asc (default → omit)

        const callArgs = vi.mocked(router.reload).mock.calls[0]?.[0];
        const data = callArgs?.data as Record<string, unknown>;

        expect(data.page).toBeNull(); // page=1 default
        expect(data.perPage).toBeNull(); // perPage=20 default
        expect(data.search).toBeNull(); // empty
        expect(data.sortKey).toBe('fullName');
        expect(data.sortDirection).toBeNull(); // asc default + sortKey set → omit
    });

    it('buildQueryData expose sortDirection desc', () => {
        const state = createState();
        state.setSort('fullName');
        state.setSort('fullName'); // desc

        const callArgs = vi.mocked(router.reload).mock.calls[1]?.[0];
        const data = callArgs?.data as Record<string, unknown>;

        expect(data.sortDirection).toBe('desc');
    });

    it('isReloading toggle via callbacks onStart / onFinish', () => {
        const state = createState();
        state.setPage(2);

        const callArgs = vi.mocked(router.reload).mock.calls[0]?.[0];
        expect(state.isReloading.value).toBe(false);

        callArgs?.onStart?.({} as never);
        expect(state.isReloading.value).toBe(true);

        callArgs?.onFinish?.({} as never);
        expect(state.isReloading.value).toBe(false);
    });

    it('only est passé au router.reload', () => {
        const state = createState({ only: ['contracts', 'meta'] });
        state.setPage(2);

        expect(router.reload).toHaveBeenCalledWith(
            expect.objectContaining({
                only: ['contracts', 'meta'],
            }),
        );
    });
});
