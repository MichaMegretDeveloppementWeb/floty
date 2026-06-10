import { describe, expect, it } from 'vitest';
import {
    useVehicleEventsTimelineFilter,
    vehicleEventOverlapsWindow,
} from '@/Composables/Vehicle/Show/useVehicleEventsTimelineFilter';

type VehicleEvent = App.Data.User.VehicleEvent.VehicleEventData;

const YEAR_2025 = { from: '2025-01-01', to: '2025-12-31' };

function makeEvent(
    overrides: Partial<VehicleEvent> & { id: number; startDate: string },
): VehicleEvent {
    return {
        vehicleId: 1,
        title: 'Entretien courant',
        categories: [],
        details: [],
        hasFiscalImpact: false,
        impliesUnavailability: true,
        endDate: overrides.startDate,
        description: null,
        garage: null,
        postalCode: null,
        amountCents: null,
        daysCount: 1,
        isReadOnly: false,
        documents: [],
        ...overrides,
    };
}

describe('vehicleEventOverlapsWindow', () => {
    it('retourne true pour un événement entièrement dans la fenêtre', () => {
        expect(vehicleEventOverlapsWindow(
            { startDate: '2025-03-01', endDate: '2025-03-10' },
            YEAR_2025.from,
            YEAR_2025.to,
        )).toBe(true);
    });

    it('retourne false pour un événement entièrement avant la fenêtre', () => {
        expect(vehicleEventOverlapsWindow(
            { startDate: '2024-11-01', endDate: '2024-12-31' },
            YEAR_2025.from,
            YEAR_2025.to,
        )).toBe(false);
    });

    it('retourne false pour un événement entièrement après la fenêtre', () => {
        expect(vehicleEventOverlapsWindow(
            { startDate: '2026-01-01', endDate: '2026-02-01' },
            YEAR_2025.from,
            YEAR_2025.to,
        )).toBe(false);
    });

    it('retourne true pour un événement à cheval sur le début de la fenêtre', () => {
        expect(vehicleEventOverlapsWindow(
            { startDate: '2024-12-20', endDate: '2025-01-05' },
            YEAR_2025.from,
            YEAR_2025.to,
        )).toBe(true);
    });

    it('retourne true pour un événement à cheval sur la fin de la fenêtre', () => {
        expect(vehicleEventOverlapsWindow(
            { startDate: '2025-12-20', endDate: '2026-01-10' },
            YEAR_2025.from,
            YEAR_2025.to,
        )).toBe(true);
    });

    it('traite un événement en cours (endDate null) comme ouvert vers le futur', () => {
        // Commencé en 2024, toujours en cours : chevauche 2025.
        expect(vehicleEventOverlapsWindow(
            { startDate: '2024-06-01', endDate: null },
            YEAR_2025.from,
            YEAR_2025.to,
        )).toBe(true);
    });

    it('retourne false pour un événement en cours commencé après la fenêtre', () => {
        expect(vehicleEventOverlapsWindow(
            { startDate: '2026-06-01', endDate: null },
            YEAR_2025.from,
            YEAR_2025.to,
        )).toBe(false);
    });
});

describe('useVehicleEventsTimelineFilter · filtre nature + total', () => {
    const events: VehicleEvent[] = [
        makeEvent({ id: 1, startDate: '2026-03-01', title: 'Entretien courant', categories: ['Entretien'], amountCents: 10000 }),
        makeEvent({ id: 2, startDate: '2026-04-01', title: 'Lavage', categories: ['Contrôle', 'Entretien'], amountCents: 8500 }),
        makeEvent({ id: 3, startDate: '2026-05-01', title: 'Vol du véhicule', categories: ['Vol'], amountCents: null }),
        makeEvent({ id: 4, startDate: '2026-02-01', title: 'Sortie de flotte', isReadOnly: true, categories: ['Cycle de vie'] }),
    ];

    it('expose les natures réellement présentes, triées', () => {
        const f = useVehicleEventsTimelineFilter(() => events, 2026);

        expect(f.categoryOptions.value.map((o) => o.value)).toEqual(['Contrôle', 'Cycle de vie', 'Entretien', 'Vol']);
    });

    it('filtre par nature et somme le coût du jeu filtré', () => {
        const f = useVehicleEventsTimelineFilter(() => events, 2026);

        f.selectedCategories.value = ['Contrôle'];

        expect(f.filteredEvents.value.map((e) => e.id)).toEqual([2]);
        expect(f.totalAmountCents.value).toBe(8500);
        expect(f.isFiltered.value).toBe(true);
    });

    it('filtre par nature (OU dans l\'axe, insensible à la casse)', () => {
        const f = useVehicleEventsTimelineFilter(() => events, 2026);

        f.selectedCategories.value = ['entretien', 'vol'];

        expect(f.filteredEvents.value.map((e) => e.id).sort()).toEqual([1, 2, 3]);
        // Entretien (events 1+2) OU Vol (event 3) → les trois.
        expect(f.totalAmountCents.value).toBe(18500);
    });

    it('expose des chips actifs supprimables et un compteur d\'axes', () => {
        const f = useVehicleEventsTimelineFilter(() => events, 2026);

        f.selectedCategories.value = ['Vol', 'Entretien'];

        expect(f.activeAxisCount.value).toBe(2);
        expect(f.activeFilterChips.value.map((c) => c.key)).toEqual(['category:Vol', 'category:Entretien']);
        expect(f.activeFilterChips.value[0]?.label).toBe('Nature : Vol');

        f.removeFilterChip('category:Vol');
        expect(f.selectedCategories.value).toEqual(['Entretien']);

        f.clearAxisFilters();
        expect(f.activeAxisCount.value).toBe(0);
    });
});

describe('useVehicleEventsTimelineFilter · recherche garage / code postal', () => {
    const events: VehicleEvent[] = [
        makeEvent({ id: 1, startDate: '2026-03-01', garage: 'Garage Martin', postalCode: '91100' }),
        makeEvent({ id: 2, startDate: '2026-04-01', garage: 'Carrosserie Dupont', postalCode: '75011' }),
        makeEvent({ id: 3, startDate: '2026-05-01' }),
    ];

    it('matche le garage en « contient », insensible à la casse', () => {
        const f = useVehicleEventsTimelineFilter(() => events, 2026);

        f.searchTerm.value = 'martin';

        expect(f.filteredEvents.value.map((e) => e.id)).toEqual([1]);
        expect(f.isFiltered.value).toBe(true);
    });

    it('matche le code postal en « contient » (91 → 91100)', () => {
        const f = useVehicleEventsTimelineFilter(() => events, 2026);

        f.searchTerm.value = '91';

        expect(f.filteredEvents.value.map((e) => e.id)).toEqual([1]);
    });

    it('exclut les événements sans garage ni code postal quand un terme est saisi', () => {
        const f = useVehicleEventsTimelineFilter(() => events, 2026);

        f.searchTerm.value = '75';

        expect(f.filteredEvents.value.map((e) => e.id)).toEqual([2]);
    });

    it('clearAxisFilters efface aussi la recherche', () => {
        const f = useVehicleEventsTimelineFilter(() => events, 2026);

        f.searchTerm.value = '91';
        f.clearAxisFilters();

        expect(f.searchTerm.value).toBe('');
        expect(f.filteredEvents.value).toHaveLength(3);
    });
});
