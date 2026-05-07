import { describe, expect, it } from 'vitest';
import {
    badgeForDeclaration,
    formatDeclarationStatus,
} from '@/Utils/format/declarationStatus';

describe('formatDeclarationStatus', () => {
    it.each([
        ['draft', 'Brouillon', 'slate'],
        ['deferred', 'Mise de côté', 'amber'],
        ['generated', 'Générée', 'emerald'],
    ] as const)('mappe "%s" → "%s" / tone %s', (status, label, tone) => {
        const badge = formatDeclarationStatus(status);

        expect(badge.label).toBe(label);
        expect(badge.tone).toBe(tone);
    });
});

describe('badgeForDeclaration', () => {
    it('renvoie tone rose et label Obsolète quand isObsolete=true peu importe le statut', () => {
        const badge = badgeForDeclaration('generated', true);

        expect(badge.label).toBe('Obsolète');
        expect(badge.tone).toBe('rose');
    });

    it('délègue à formatDeclarationStatus si non obsolète', () => {
        const badge = badgeForDeclaration('draft', false);

        expect(badge.label).toBe('Brouillon');
        expect(badge.tone).toBe('slate');
    });
});
