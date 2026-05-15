import { describe, expect, it } from 'vitest';
import {
    divergenceTooltip,
    entryHasDivergence,
} from '@/Utils/Billing/divergenceDetection';

type Entry = App.Data.User.Billing.MonthlyBillingBreakdownData['entries'][number];

const baseEntry: Entry = {
    month: 3,
    daysUsed: 10,
    totalCents: 77_000,
    hasMissingPricing: false,
    existingInvoiceId: 42,
    existingInvoiceNumber: '2024-03-0001',
    invoicedDaysUsed: 10,
    invoicedTotalCents: 77_000,
};

describe('entryHasDivergence', () => {
    it('retourne false quand aucune facture n\'est émise', () => {
        const entry: Entry = { ...baseEntry, existingInvoiceId: null, existingInvoiceNumber: null };

        expect(entryHasDivergence(entry)).toBe(false);
    });

    it('retourne false quand snapshot et recalcul actuel sont identiques', () => {
        expect(entryHasDivergence(baseEntry)).toBe(false);
    });

    it('retourne true quand les jours diffèrent', () => {
        const entry: Entry = { ...baseEntry, daysUsed: 11 };

        expect(entryHasDivergence(entry)).toBe(true);
    });

    it('retourne true quand le total cents diffère', () => {
        const entry: Entry = { ...baseEntry, totalCents: 80_000 };

        expect(entryHasDivergence(entry)).toBe(true);
    });

    it('retourne false si invoicedDaysUsed est null (snapshot incomplet)', () => {
        const entry: Entry = { ...baseEntry, invoicedDaysUsed: null };

        expect(entryHasDivergence(entry)).toBe(false);
    });
});

describe('divergenceTooltip', () => {
    it('compose un message FR avec snapshot + recalcul + invitation à régénérer', () => {
        const entry: Entry = { ...baseEntry, daysUsed: 12, totalCents: 80_000 };

        const result = divergenceTooltip(entry);

        expect(result).toContain('Données obsolètes');
        expect(result).toContain('10 j');
        expect(result).toContain('12 j');
        expect(result).toContain('Régénérez');
    });

    it('utilise 0 et 0,00 € quand les valeurs nulles sont présentes', () => {
        const entry: Entry = {
            ...baseEntry,
            invoicedDaysUsed: null,
            invoicedTotalCents: null,
            totalCents: null,
        };

        const result = divergenceTooltip(entry);

        expect(result).toContain('0 j');
    });
});
