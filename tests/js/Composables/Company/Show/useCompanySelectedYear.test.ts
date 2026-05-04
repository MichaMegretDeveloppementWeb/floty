import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { defineComponent, h, nextTick, ref } from 'vue';
import { useCompanySelectedYear } from '@/Composables/Company/Show/useCompanySelectedYear';

type YearStats = App.Data.User.Company.CompanyYearStatsData;

function makeStats(year: number, daysUsed: number): YearStats {
    return {
        year,
        daysUsed,
        contractsCount: 1,
        lcdCount: 1,
        lldCount: 0,
        annualTaxDue: 100,
        rent: null,
    };
}

/**
 * Le composable utilise `onMounted` ; pour le tester il faut le monter
 * dans un composant éphémère plutôt que l'appeler directement (sinon
 * le hook lifecycle ne se déclenche pas).
 */
function mountComposable(opts: {
    history?: YearStats[];
    availableYears?: number[];
    currentRealYear?: number;
    initialUrl?: string;
}) {
    if (typeof window !== 'undefined' && opts.initialUrl) {
        window.history.replaceState({}, '', opts.initialUrl);
    }

    let captured: ReturnType<typeof useCompanySelectedYear> | null = null;

    const history = ref<readonly YearStats[]>(opts.history ?? []);
    const availableYears = ref<readonly number[]>(opts.availableYears ?? []);
    const currentRealYear = ref<number>(opts.currentRealYear ?? 2026);

    const Wrapper = defineComponent({
        setup() {
            captured = useCompanySelectedYear({ history, availableYears, currentRealYear });

            return () => h('div');
        },
    });

    const wrapper = mount(Wrapper);

    return {
        ctx: captured!,
        wrapper,
    };
}

describe('useCompanySelectedYear', () => {
    it('initialise selectedYear sur currentRealYear quand pas de query param', async () => {
        const { ctx } = mountComposable({
            initialUrl: '/companies/1',
            currentRealYear: 2026,
        });

        await nextTick();
        expect(ctx.selectedYear.value).toBe(2026);
    });

    it('lit ?year=2024 du query param au mount', async () => {
        const { ctx } = mountComposable({
            initialUrl: '/companies/1?year=2024',
            currentRealYear: 2026,
        });

        await nextTick();
        expect(ctx.selectedYear.value).toBe(2024);
    });

    it('retourne currentRealYear quand le query param est invalide', async () => {
        const { ctx } = mountComposable({
            initialUrl: '/companies/1?year=foo',
            currentRealYear: 2026,
        });

        await nextTick();
        expect(ctx.selectedYear.value).toBe(2026);
    });

    it('byYear retourne les stats du history correspondant à selectedYear', async () => {
        const stats2024 = makeStats(2024, 200);
        const stats2025 = makeStats(2025, 150);
        const { ctx } = mountComposable({
            initialUrl: '/companies/1?year=2024',
            history: [stats2024, stats2025],
            availableYears: [2024, 2025],
            currentRealYear: 2026,
        });

        await nextTick();
        expect(ctx.byYear.value.year).toBe(2024);
        expect(ctx.byYear.value.daysUsed).toBe(200);
    });

    it('byYear retourne des stats vides pour une année hors history', async () => {
        const { ctx } = mountComposable({
            initialUrl: '/companies/1?year=2030',
            history: [makeStats(2024, 200)],
            availableYears: [2024],
            currentRealYear: 2026,
        });

        await nextTick();
        expect(ctx.byYear.value.year).toBe(2030);
        expect(ctx.byYear.value.daysUsed).toBe(0);
        expect(ctx.byYear.value.contractsCount).toBe(0);
        expect(ctx.byYear.value.annualTaxDue).toBe(0);
        expect(ctx.byYear.value.rent).toBeNull();
    });

    it('setSelectedYear met à jour byYear automatiquement', async () => {
        const { ctx } = mountComposable({
            initialUrl: '/companies/1',
            history: [makeStats(2024, 200), makeStats(2026, 100)],
            availableYears: [2024, 2026],
            currentRealYear: 2026,
        });

        await nextTick();
        expect(ctx.byYear.value.year).toBe(2026);
        expect(ctx.byYear.value.daysUsed).toBe(100);

        ctx.setSelectedYear(2024);
        await nextTick();

        expect(ctx.selectedYear.value).toBe(2024);
        expect(ctx.byYear.value.year).toBe(2024);
        expect(ctx.byYear.value.daysUsed).toBe(200);
    });

    it('setSelectedYear sérialise dans l\'URL', async () => {
        const { ctx } = mountComposable({
            initialUrl: '/companies/1',
            currentRealYear: 2026,
        });

        await nextTick();
        ctx.setSelectedYear(2024);
        await nextTick();

        expect(window.location.search).toContain('year=2024');
    });

    it('setSelectedYear(currentRealYear) retire le query param de l\'URL', async () => {
        const { ctx } = mountComposable({
            initialUrl: '/companies/1?year=2024',
            currentRealYear: 2026,
        });

        await nextTick();
        ctx.setSelectedYear(2026);
        await nextTick();

        expect(window.location.search).not.toContain('year=');
    });
});
