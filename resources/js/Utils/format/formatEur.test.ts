import { describe, expect, it } from 'vitest';
import { formatEur } from './formatEur';

// NNBSP (U+202F) — séparateur de milliers / espace avant le symbole €
// produit par `Intl.NumberFormat('fr-FR', ...)`.
const NNBSP = ' ';

describe('formatEur', () => {
    it('formate 0 sans décimales par défaut', () => {
        expect(formatEur(0)).toBe(`0${NNBSP}€`);
    });

    it('formate un montant entier avec séparateur de milliers', () => {
        expect(formatEur(15500)).toBe(`15${NNBSP}500${NNBSP}€`);
    });

    it('arrondit les décimales par défaut (0 chiffre)', () => {
        expect(formatEur(89.51)).toBe(`90${NNBSP}€`);
    });

    it('respecte fractionDigits=2 pour les montants détaillés', () => {
        expect(formatEur(89.51, 2)).toBe(`89,51${NNBSP}€`);
    });

    it('formate un montant négatif', () => {
        expect(formatEur(-100)).toBe(`-100${NNBSP}€`);
    });
});
