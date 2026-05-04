import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { defineComponent, h, nextTick, ref } from 'vue';
import { useCompanySelectedYear } from '@/Composables/Company/Show/useCompanySelectedYear';

type ActivityYear = App.Data.User.Company.CompanyActivityYearData;

function makeActivity(year: number, monthMar: number, vehicleCount: number = 0): ActivityYear {
    const daysByMonth = [0, 0, monthMar, 0, 0, 0, 0, 0, 0, 0, 0, 0];
    const topVehicles = Array.from({ length: vehicleCount }, (_, i) => ({
        vehicleId: i + 1,
        licensePlate: `AAA-00${i + 1}-AA`,
        brand: 'Renault',
        model: 'Clio',
        daysUsed: 10 - i,
        percentage: 100 - i * 10,
    }));

    return { year, daysByMonth, topVehicles };
}

function mountComposable(opts: {
    activityByYear?: ActivityYear[];
    availableYears?: number[];
    currentRealYear?: number;
    initialUrl?: string;
}) {
    if (typeof window !== 'undefined' && opts.initialUrl) {
        window.history.replaceState({}, '', opts.initialUrl);
    }

    let captured: ReturnType<typeof useCompanySelectedYear> | null = null;

    const activityByYear = ref<readonly ActivityYear[]>(opts.activityByYear ?? []);
    const availableYears = ref<readonly number[]>(opts.availableYears ?? []);
    const currentRealYear = ref<number>(opts.currentRealYear ?? 2026);

    const Wrapper = defineComponent({
        setup() {
            captured = useCompanySelectedYear({
                activityByYear,
                availableYears,
                currentRealYear,
            });

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

    it('byYear retourne l\'activité correspondant à selectedYear', async () => {
        const activity2024 = makeActivity(2024, 5, 2);
        const activity2025 = makeActivity(2025, 8, 3);
        const { ctx } = mountComposable({
            initialUrl: '/companies/1?year=2024',
            activityByYear: [activity2024, activity2025],
            availableYears: [2024, 2025],
            currentRealYear: 2026,
        });

        await nextTick();
        expect(ctx.byYear.value.year).toBe(2024);
        expect(ctx.byYear.value.daysByMonth[2]).toBe(5);
        expect(ctx.byYear.value.topVehicles).toHaveLength(2);
    });

    it('byYear retourne une activité vide pour une année hors plage', async () => {
        const { ctx } = mountComposable({
            initialUrl: '/companies/1?year=2030',
            activityByYear: [makeActivity(2024, 5, 1)],
            availableYears: [2024],
            currentRealYear: 2026,
        });

        await nextTick();
        expect(ctx.byYear.value.year).toBe(2030);
        expect(ctx.byYear.value.daysByMonth).toEqual([0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]);
        expect(ctx.byYear.value.topVehicles).toHaveLength(0);
    });

    it('setSelectedYear met à jour byYear automatiquement', async () => {
        const { ctx } = mountComposable({
            initialUrl: '/companies/1',
            activityByYear: [makeActivity(2024, 5, 1), makeActivity(2026, 12, 2)],
            availableYears: [2024, 2026],
            currentRealYear: 2026,
        });

        await nextTick();
        expect(ctx.byYear.value.year).toBe(2026);
        expect(ctx.byYear.value.daysByMonth[2]).toBe(12);

        ctx.setSelectedYear(2024);
        await nextTick();

        expect(ctx.selectedYear.value).toBe(2024);
        expect(ctx.byYear.value.year).toBe(2024);
        expect(ctx.byYear.value.daysByMonth[2]).toBe(5);
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
