import { describe, expect, it } from 'vitest';
import { countConflictDaysInRange } from '@/Composables/Vehicle/Show/useUnavailabilityForm';

/**
 * Tests de la fonction pure qui alimente l'encart info de la modale
 * indispo (cohabitation indispo↔contrat, ADR-0019). La fonction est
 * extraite du composable pour permettre un test unitaire sans monter
 * `useForm` Inertia.
 */
describe('countConflictDaysInRange', () => {
    it('retourne 0 quand startDate est null', () => {
        expect(countConflictDaysInRange(['2024-05-10'], null, '2024-05-20', false)).toBe(0);
    });

    it('retourne 0 quand endDate est null et ongoing est false', () => {
        expect(countConflictDaysInRange(['2024-05-10'], '2024-05-01', null, false)).toBe(0);
    });

    it('retourne 0 quand busyDates est vide', () => {
        expect(countConflictDaysInRange([], '2024-05-01', '2024-05-31', false)).toBe(0);
    });

    it('compte les dates dans la plage inclusive [start, end]', () => {
        const busy = ['2024-05-09', '2024-05-10', '2024-05-15', '2024-05-20', '2024-05-21'];
        // plage 2024-05-10 → 2024-05-20 inclusive
        // doit compter 10, 15, 20 = 3
        expect(countConflictDaysInRange(busy, '2024-05-10', '2024-05-20', false)).toBe(3);
    });

    it('exclut les dates avant startDate', () => {
        const busy = ['2024-05-01', '2024-05-02', '2024-05-15'];
        expect(countConflictDaysInRange(busy, '2024-05-10', '2024-05-31', false)).toBe(1);
    });

    it('exclut les dates après endDate', () => {
        const busy = ['2024-05-15', '2024-06-01', '2024-06-15'];
        expect(countConflictDaysInRange(busy, '2024-05-01', '2024-05-31', false)).toBe(1);
    });

    it('compte toutes les dates ≥ startDate quand ongoing est true', () => {
        // ongoing = true : la plage est ouverte sur le futur, endDate
        // est ignoré (cohérent avec backend `end_date IS NULL`).
        const busy = ['2024-04-30', '2024-05-01', '2024-05-15', '2024-12-31', '2025-06-01'];
        // depuis 2024-05-01 inclus → 4 dates (sans 2024-04-30)
        expect(countConflictDaysInRange(busy, '2024-05-01', null, true)).toBe(4);
    });

    it('ignore endDate quand ongoing est true (preuve que la borne sup est désactivée)', () => {
        const busy = ['2024-05-15', '2024-06-15', '2024-07-15'];
        // endDate explicite ne doit pas raccourcir la plage
        expect(countConflictDaysInRange(busy, '2024-05-01', '2024-05-31', true)).toBe(3);
    });

    it('retourne 0 quand aucune date busy ne tombe dans la plage', () => {
        const busy = ['2024-01-01', '2024-12-31'];
        expect(countConflictDaysInRange(busy, '2024-05-01', '2024-05-31', false)).toBe(0);
    });

    it('compte correctement quand la plage est un seul jour', () => {
        const busy = ['2024-05-15', '2024-05-16'];
        expect(countConflictDaysInRange(busy, '2024-05-15', '2024-05-15', false)).toBe(1);
    });
});
