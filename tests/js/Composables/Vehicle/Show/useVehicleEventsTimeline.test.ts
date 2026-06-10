import { describe, expect, it } from 'vitest';
import {
    buildVehicleEventsTimeline,
    formatVehicleEventDaySpan,
} from '@/Composables/Vehicle/Show/useVehicleEventsTimeline';
import type { VehicleEventTimelineEntry } from '@/Composables/Vehicle/Show/useVehicleEventsTimeline';

type VehicleEvent = App.Data.User.VehicleEvent.VehicleEventData;

function makeEvent(overrides: Partial<VehicleEvent> & { id: number; startDate: string }): VehicleEvent {
    return {
        vehicleId: 1,
        title: 'Entretien courant',
        categories: [],
        details: [],
        hasFiscalImpact: false,
        impliesUnavailability: true,
        endDate: null,
        description: null,
        amountCents: null,
        daysCount: 0,
        isReadOnly: false,
        documents: [],
        ...overrides,
    };
}

describe('buildVehicleEventsTimeline', () => {
    it('déplie un événement clos de 3 jours en 3 jours (jour 1/3, 2/3, 3/3)', () => {
        const event = makeEvent({ id: 1, startDate: '2026-05-10', endDate: '2026-05-12' });

        const days = buildVehicleEventsTimeline([event]);

        // Trois jours, du plus récent au plus ancien.
        expect(days.map((d) => d.date)).toEqual(['2026-05-12', '2026-05-11', '2026-05-10']);
        expect(days[0]!.entries[0]).toMatchObject({ dayIndex: 3, dayCount: 3, isOngoing: false, collapsed: false });
        expect(days[1]!.entries[0]).toMatchObject({ dayIndex: 2, dayCount: 3 });
        expect(days[2]!.entries[0]).toMatchObject({ dayIndex: 1, dayCount: 3 });
    });

    it('rend un événement en cours en une seule entrée sur son jour de début', () => {
        const event = makeEvent({ id: 1, startDate: '2026-05-10', endDate: null });

        const days = buildVehicleEventsTimeline([event]);

        expect(days).toHaveLength(1);
        expect(days[0]!.date).toBe('2026-05-10');
        expect(days[0]!.entries[0]).toMatchObject({ dayIndex: 1, dayCount: null, isOngoing: true, collapsed: false });
    });

    it('rend un événement d\'un seul jour en jour 1/1', () => {
        const event = makeEvent({ id: 1, startDate: '2026-05-10', endDate: '2026-05-10' });

        const days = buildVehicleEventsTimeline([event]);

        expect(days).toHaveLength(1);
        expect(days[0]!.entries[0]).toMatchObject({ dayIndex: 1, dayCount: 1 });
    });

    it('regroupe plusieurs événements du même jour, triés par id croissant', () => {
        const a = makeEvent({ id: 7, startDate: '2026-05-10', endDate: '2026-05-10' });
        const b = makeEvent({ id: 3, startDate: '2026-05-10', endDate: '2026-05-10' });

        const days = buildVehicleEventsTimeline([a, b]);

        expect(days).toHaveLength(1);
        expect(days[0]!.entries.map((e) => e.event.id)).toEqual([3, 7]);
    });

    it('ordonne les jours du plus récent au plus ancien à travers plusieurs événements', () => {
        const old = makeEvent({ id: 1, startDate: '2026-01-05', endDate: '2026-01-05' });
        const recent = makeEvent({ id: 2, startDate: '2026-09-20', endDate: '2026-09-20' });

        const days = buildVehicleEventsTimeline([old, recent]);

        expect(days.map((d) => d.date)).toEqual(['2026-09-20', '2026-01-05']);
    });

    it('replie un événement clos anormalement long (> 366 jours) sur son jour de début', () => {
        // 2020-01-01 → 2022-01-01 = 732 jours inclus.
        const event = makeEvent({ id: 1, startDate: '2020-01-01', endDate: '2022-01-01' });

        const days = buildVehicleEventsTimeline([event]);

        expect(days).toHaveLength(1);
        expect(days[0]!.date).toBe('2020-01-01');
        expect(days[0]!.entries[0]).toMatchObject({ dayIndex: 1, dayCount: 732, isOngoing: false, collapsed: true });
    });

    it('retourne une liste vide quand il n\'y a aucun événement', () => {
        expect(buildVehicleEventsTimeline([])).toEqual([]);
    });

    it('ne montre que les jours dans la fenêtre (clamp), jour X/N relatif à l\'événement complet', () => {
        // Événement 02/03 → 30/03 (29 jours), fenêtre 10/03 → 22/03.
        const event = makeEvent({ id: 1, startDate: '2026-03-02', endDate: '2026-03-30' });

        const days = buildVehicleEventsTimeline([event], { from: '2026-03-10', to: '2026-03-22' });

        // 13 jours affichés (10 au 22 inclus), du plus récent au plus ancien.
        expect(days).toHaveLength(13);
        expect(days[0]!.date).toBe('2026-03-22');
        expect(days[12]!.date).toBe('2026-03-10');
        // Position conservée dans l'événement complet : 22/03 = jour 21/29, 10/03 = jour 9/29.
        expect(days[0]!.entries[0]).toMatchObject({ dayIndex: 21, dayCount: 29 });
        expect(days[12]!.entries[0]).toMatchObject({ dayIndex: 9, dayCount: 29 });
    });

    it('clampe un événement en cours commencé avant la fenêtre sur le début de fenêtre', () => {
        const event = makeEvent({ id: 1, startDate: '2026-03-02', endDate: null });

        const days = buildVehicleEventsTimeline([event], { from: '2026-03-10', to: '2026-03-22' });

        expect(days).toHaveLength(1);
        expect(days[0]!.date).toBe('2026-03-10');
        expect(days[0]!.entries[0]).toMatchObject({ isOngoing: true, dayCount: null });
    });

    it('sans fenêtre, affiche tous les jours (rétrocompatibilité)', () => {
        const event = makeEvent({ id: 1, startDate: '2026-03-02', endDate: '2026-03-04' });

        expect(buildVehicleEventsTimeline([event])).toHaveLength(3);
    });
});

describe('formatVehicleEventDaySpan', () => {
    const baseEntry = (
        overrides: Partial<VehicleEventTimelineEntry>,
    ): VehicleEventTimelineEntry => ({
        event: makeEvent({ id: 1, startDate: '2026-05-10', endDate: '2026-05-12' }),
        dayIndex: 1,
        dayCount: 3,
        isOngoing: false,
        collapsed: false,
        ...overrides,
    });

    it('rend « en cours » pour un événement ouvert', () => {
        expect(formatVehicleEventDaySpan(baseEntry({ isOngoing: true, dayCount: null }))).toBe('en cours');
    });

    it('rend « jour X/N » pour un événement multi-jours', () => {
        expect(formatVehicleEventDaySpan(baseEntry({ dayIndex: 2, dayCount: 3 }))).toBe('jour 2/3');
    });

    it('ne rend aucun label pour un événement d\'un seul jour', () => {
        expect(formatVehicleEventDaySpan(baseEntry({ dayIndex: 1, dayCount: 1 }))).toBe('');
    });

    it('rend la période complète pour un événement replié', () => {
        const entry = baseEntry({
            event: makeEvent({ id: 1, startDate: '2020-01-01', endDate: '2022-01-01' }),
            dayCount: 732,
            collapsed: true,
        });
        expect(formatVehicleEventDaySpan(entry)).toBe('du 01/01/2020 au 01/01/2022 · 732 jours');
    });
});
