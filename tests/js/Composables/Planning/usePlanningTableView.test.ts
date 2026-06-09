import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { defineComponent, h, nextTick, ref } from 'vue';
import { usePlanningTableView } from '@/Composables/Planning/usePlanningTableView';
import type { SortDirection } from '@/Composables/Shared/useLocalSortDirection';

type Row = { licensePlate: string; brand: string; model: string };

function row(licensePlate: string, brand: string, model: string): Row {
    return { licensePlate, brand, model };
}

function mountComposable(opts: {
    vehicles: Row[];
    initialDirection?: SortDirection;
    initialUrl?: string;
}) {
    if (typeof window !== 'undefined') {
        window.history.replaceState({}, '', opts.initialUrl ?? '/app/planning');
    }

    let captured: ReturnType<typeof usePlanningTableView<Row>> | null = null;
    const vehicles = ref<Row[]>(opts.vehicles);

    const Wrapper = defineComponent({
        setup() {
            captured = usePlanningTableView(vehicles, {
                initialDirection: opts.initialDirection ?? 'asc',
            });

            return () => h('div');
        },
    });

    const wrapper = mount(Wrapper);

    return { ctx: captured!, vehicles, wrapper };
}

function plates(rows: readonly Row[]): string[] {
    return rows.map((r) => r.licensePlate);
}

describe('usePlanningTableView', () => {
    it('trie par plaque en ascendant par défaut', async () => {
        const { ctx } = mountComposable({
            vehicles: [
                row('CC-300-CC', 'Peugeot', '208'),
                row('AA-100-AA', 'Renault', 'Clio'),
                row('BB-200-BB', 'Citroën', 'C3'),
            ],
        });

        await nextTick();
        expect(plates(ctx.displayedVehicles.value)).toEqual([
            'AA-100-AA',
            'BB-200-BB',
            'CC-300-CC',
        ]);
    });

    it("toggleSort inverse l'ordre", async () => {
        const { ctx } = mountComposable({
            vehicles: [
                row('AA-100-AA', 'Renault', 'Clio'),
                row('BB-200-BB', 'Citroën', 'C3'),
            ],
        });

        await nextTick();
        ctx.toggleSort();
        await nextTick();

        expect(ctx.direction.value).toBe('desc');
        expect(plates(ctx.displayedVehicles.value)).toEqual([
            'BB-200-BB',
            'AA-100-AA',
        ]);
    });

    it('filtre par plaque (sous-chaîne)', async () => {
        const { ctx } = mountComposable({
            vehicles: [
                row('AA-123-BB', 'Renault', 'Clio'),
                row('CC-456-DD', 'Peugeot', '208'),
            ],
        });

        await nextTick();
        ctx.search.value = '123';
        await nextTick();

        expect(plates(ctx.displayedVehicles.value)).toEqual(['AA-123-BB']);
    });

    it('filtre par marque/modèle insensible à la casse et aux accents', async () => {
        const { ctx } = mountComposable({
            vehicles: [
                row('AA-100-AA', 'Citroën', 'C3'),
                row('BB-200-BB', 'Renault', 'Clio'),
            ],
        });

        await nextTick();

        ctx.search.value = 'citroen';
        await nextTick();
        expect(plates(ctx.displayedVehicles.value)).toEqual(['AA-100-AA']);

        ctx.search.value = 'CLIO';
        await nextTick();
        expect(plates(ctx.displayedVehicles.value)).toEqual(['BB-200-BB']);
    });

    it('une recherche vide retourne tous les véhicules', async () => {
        const { ctx } = mountComposable({
            vehicles: [
                row('AA-100-AA', 'Renault', 'Clio'),
                row('BB-200-BB', 'Citroën', 'C3'),
            ],
        });

        await nextTick();
        ctx.search.value = 'zzz';
        await nextTick();
        expect(ctx.displayedVehicles.value).toHaveLength(0);

        ctx.search.value = '';
        await nextTick();
        expect(ctx.displayedVehicles.value).toHaveLength(2);
    });

    it('lit ?search= et ?direction= au mount', async () => {
        const { ctx } = mountComposable({
            vehicles: [
                row('AA-100-AA', 'Renault', 'Clio'),
                row('BB-200-BB', 'Citroën', 'C3'),
            ],
            initialDirection: 'desc',
            initialUrl: '/app/planning?search=clio&direction=desc',
        });

        await nextTick();
        expect(ctx.search.value).toBe('clio');
        expect(plates(ctx.displayedVehicles.value)).toEqual(['AA-100-AA']);
    });

    it("synchronise ?search= et ?direction= dans l'URL sans rechargement", async () => {
        const { ctx } = mountComposable({
            vehicles: [row('AA-100-AA', 'Renault', 'Clio')],
        });

        await nextTick();

        ctx.search.value = 'ren';
        await nextTick();
        expect(window.location.search).toContain('search=ren');

        ctx.toggleSort();
        await nextTick();
        expect(window.location.search).toContain('direction=desc');

        ctx.search.value = '';
        await nextTick();
        expect(window.location.search).not.toContain('search=');
    });
});
