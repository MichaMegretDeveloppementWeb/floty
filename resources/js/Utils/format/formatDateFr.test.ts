import { describe, expect, it } from 'vitest';
import { formatDateFr } from './formatDateFr';

describe('formatDateFr', () => {
    it('convertit ISO Y-m-d en d/m/Y', () => {
        expect(formatDateFr('2024-03-15')).toBe('15/03/2024');
    });

    it('préserve les zéros initiaux', () => {
        expect(formatDateFr('2024-01-05')).toBe('05/01/2024');
    });

    it("ne fait pas de parsing Date (pas d'effet de timezone)", () => {
        // 2024-12-31 doit rester 31/12/2024 même si UTC est différent
        expect(formatDateFr('2024-12-31')).toBe('31/12/2024');
    });
});
