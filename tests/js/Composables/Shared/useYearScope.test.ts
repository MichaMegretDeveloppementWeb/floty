import { router } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h, nextTick } from 'vue';
import { useYearScope } from '@/Composables/Shared/useYearScope';
import type { UseYearScopeOptions } from '@/Composables/Shared/useYearScope';

type YearScope = App.Data.Shared.YearScopeData;

function makeScope(overrides: Partial<YearScope> = {}): YearScope {
    return {
        currentYear: 2026,
        minYear: 2024,
        availableYears: [2024, 2025, 2026],
        ...overrides,
    };
}

function mountComposable(
    scope: YearScope,
    opts?: UseYearScopeOptions,
    initialUrl?: string,
) {
    if (typeof window !== 'undefined') {
        window.history.replaceState({}, '', initialUrl ?? '/');
    }

    let captured: ReturnType<typeof useYearScope> | null = null;

    const Wrapper = defineComponent({
        setup() {
            captured = useYearScope(scope, opts);

            return () => h('div');
        },
    });

    const wrapper = mount(Wrapper);

    return { ctx: captured!, wrapper };
}

describe('useYearScope', () => {
    beforeEach(() => {
        vi.spyOn(router, 'get').mockImplementation(() => {});
    });

    it('initialise selectedYear sur currentYear par défaut', async () => {
        const { ctx } = mountComposable(makeScope({ currentYear: 2026 }));

        await nextTick();
        expect(ctx.selectedYear.value).toBe(2026);
    });

    it('utilise opts.initialYear quand fourni et dans le scope', async () => {
        const { ctx } = mountComposable(makeScope(), { initialYear: 2024 });

        await nextTick();
        expect(ctx.selectedYear.value).toBe(2024);
    });

    it('fallback sur currentYear si opts.initialYear est hors scope', async () => {
        const { ctx } = mountComposable(makeScope({ currentYear: 2026 }), {
            initialYear: 2099,
        });

        await nextTick();
        expect(ctx.selectedYear.value).toBe(2026);
    });

    it('selectYear no-op si année hors scope', async () => {
        const { ctx } = mountComposable(makeScope());
        await nextTick();

        ctx.selectYear(2099);

        expect(ctx.selectedYear.value).toBe(2026);
        expect(router.get).not.toHaveBeenCalled();
    });

    it('mode local : selectYear met à jour l\'URL sans appeler router.get', async () => {
        const { ctx } = mountComposable(makeScope(), undefined, '/companies/1');
        await nextTick();

        ctx.selectYear(2024);
        await nextTick();

        expect(ctx.selectedYear.value).toBe(2024);
        expect(window.location.search).toContain('year=2024');
        expect(router.get).not.toHaveBeenCalled();
    });

    it('mode reload : selectYear délègue à router.get avec les keys', async () => {
        const { ctx } = mountComposable(
            makeScope(),
            { reloadKeys: ['vehicles', 'query'] },
            '/vehicles?status=active',
        );
        await nextTick();

        ctx.selectYear(2024);
        await nextTick();

        expect(ctx.selectedYear.value).toBe(2024);
        expect(router.get).toHaveBeenCalledOnce();

        // Vérifie que les keys sont bien passées à router.get
        const callArgs = vi.mocked(router.get).mock.calls[0];
        expect(callArgs).toBeDefined();
        expect(callArgs![2]).toMatchObject({
            only: ['vehicles', 'query'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    });

    it('canSelect = false si une seule année dans availableYears', async () => {
        const { ctx } = mountComposable(
            makeScope({ availableYears: [2026] }),
        );

        await nextTick();
        expect(ctx.canSelect.value).toBe(false);
    });

    it('canSelect = true si plusieurs années', async () => {
        const { ctx } = mountComposable(
            makeScope({ availableYears: [2024, 2025, 2026] }),
        );

        await nextTick();
        expect(ctx.canSelect.value).toBe(true);
    });

    it('isInScope reflète l\'appartenance à availableYears', () => {
        const { ctx } = mountComposable(
            makeScope({ availableYears: [2024, 2025, 2026] }),
        );

        expect(ctx.isInScope(2024)).toBe(true);
        expect(ctx.isInScope(2025)).toBe(true);
        expect(ctx.isInScope(2027)).toBe(false);
    });
});
