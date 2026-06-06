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
        type: 'maintenance',
        title: null,
        categories: [],
        hasFiscalImpact: false,
        impliesUnavailability: true,
        endDate: overrides.startDate,
        description: null,
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

describe('useVehicleEventsTimelineFilter · filtres type / catégorie + total', () => {
    const events: VehicleEvent[] = [
        makeEvent({ id: 1, startDate: '2026-03-01', type: 'maintenance', categories: ['Entretien'], amountCents: 10000 }),
        makeEvent({ id: 2, startDate: '2026-04-01', type: 'other', title: 'Contrôle', categories: ['Contrôle', 'Entretien'], amountCents: 8500 }),
        makeEvent({ id: 3, startDate: '2026-05-01', type: 'theft', categories: ['Vol'], amountCents: null }),
    ];

    it('expose les options de type et catégorie réellement présentes', () => {
        const f = useVehicleEventsTimelineFilter(() => events, 2026);

        expect(f.typeOptions.value.map((o) => o.value).sort()).toEqual(['maintenance', 'other', 'theft']);
        expect(f.categoryOptions.value.map((o) => o.value)).toEqual(['Contrôle', 'Entretien', 'Vol']);
    });

    it('filtre par type et somme le coût du jeu filtré', () => {
        const f = useVehicleEventsTimelineFilter(() => events, 2026);

        f.selectedTypes.value = ['maintenance'];

        expect(f.filteredEvents.value.map((e) => e.id)).toEqual([1]);
        expect(f.totalAmountCents.value).toBe(10000);
        expect(f.isFiltered.value).toBe(true);
    });

    it('filtre par catégorie (OU dans l\'axe, insensible à la casse)', () => {
        const f = useVehicleEventsTimelineFilter(() => events, 2026);

        f.selectedCategories.value = ['entretien', 'vol'];

        expect(f.filteredEvents.value.map((e) => e.id).sort()).toEqual([1, 2, 3]);
        // Entretien (events 1+2) OU Vol (event 3) → les trois.
        expect(f.totalAmountCents.value).toBe(18500);
    });

    it('combine type ET catégorie', () => {
        const f = useVehicleEventsTimelineFilter(() => events, 2026);

        f.selectedTypes.value = ['other'];
        f.selectedCategories.value = ['Contrôle'];

        expect(f.filteredEvents.value.map((e) => e.id)).toEqual([2]);
        expect(f.totalAmountCents.value).toBe(8500);
    });
});
