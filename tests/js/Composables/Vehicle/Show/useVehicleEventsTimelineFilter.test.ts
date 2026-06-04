import { describe, expect, it } from 'vitest';
import { vehicleEventOverlapsWindow } from '@/Composables/Vehicle/Show/useVehicleEventsTimelineFilter';

const YEAR_2025 = { from: '2025-01-01', to: '2025-12-31' };

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
