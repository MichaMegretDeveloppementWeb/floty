import { describe, expect, it } from 'vitest';
import { formatDayLongFr } from '@/Utils/format/formatDayLongFr';

describe('formatDayLongFr', () => {
    it('convertit ISO Y-m-d en jour long français', () => {
        // 12 juin 2026 est un vendredi.
        expect(formatDayLongFr('2026-06-12')).toBe('ven. 12 juin 2026');
    });

    it('rend correctement le 1er du mois', () => {
        // 1er janvier 2026 est un jeudi.
        expect(formatDayLongFr('2026-01-01')).toBe('jeu. 1 janvier 2026');
    });

    it('ne dérive pas en bord de timezone (lecture UTC)', () => {
        // 31 décembre 2024 est un mardi, doit rester le 31.
        expect(formatDayLongFr('2024-12-31')).toBe('mar. 31 décembre 2024');
    });
});
