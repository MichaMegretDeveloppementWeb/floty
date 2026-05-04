import { describe, expect, it } from 'vitest';
import {
    ancienneteMonths,
    formatAnciennete,
    useDriverAnciennete,
} from '@/Composables/Driver/useDriverAnciennete';

describe('ancienneteMonths', () => {
    it("retourne 0 si la date est aujourd'hui", () => {
        const today = new Date(2026, 4, 2); // 2 mai 2026
        expect(ancienneteMonths('2026-05-02', today)).toBe(0);
    });

    it('compte les mois pleins', () => {
        const today = new Date(2026, 4, 2); // 2 mai 2026
        // 1er janvier 2024 → 28 mois pleins
        expect(ancienneteMonths('2024-01-01', today)).toBe(28);
    });

    it("soustrait 1 mois si le jour du mois courant est avant le jour d'origine", () => {
        const today = new Date(2026, 4, 2); // 2 mai 2026
        // 15 janvier 2024 → 27 mois (manque 13 jours pour faire 28)
        expect(ancienneteMonths('2024-01-15', today)).toBe(27);
    });

    it('clamp à 0 si la date est dans le futur', () => {
        const today = new Date(2026, 4, 2);
        expect(ancienneteMonths('2027-01-01', today)).toBe(0);
    });
});

describe('formatAnciennete', () => {
    it.each([
        [0, "Moins d'un mois"],
        [1, '1 mois'],
        [11, '11 mois'],
        [12, '1 an'],
        [13, '1 an 1 mois'],
        [24, '2 ans'],
        [27, '2 ans 3 mois'],
    ])('formate %d mois en "%s"', (months, expected) => {
        expect(formatAnciennete(months)).toBe(expected);
    });
});

describe('useDriverAnciennete', () => {
    it('renvoie "-" pour un driver sans membership', () => {
        expect(useDriverAnciennete([])).toBe('-');
    });

    it('utilise la date la plus ancienne quand plusieurs memberships existent', () => {
        const today = new Date(2026, 4, 2);
        const memberships = [
            { joinedAt: '2025-06-01' },
            { joinedAt: '2024-01-01' }, // la plus ancienne
            { joinedAt: '2025-12-15' },
        ];
        expect(useDriverAnciennete(memberships, today)).toBe('2 ans 4 mois');
    });

    it('formate correctement pour une membership unique', () => {
        const today = new Date(2026, 4, 2);
        const memberships = [{ joinedAt: '2026-04-01' }];
        expect(useDriverAnciennete(memberships, today)).toBe('1 mois');
    });
});
