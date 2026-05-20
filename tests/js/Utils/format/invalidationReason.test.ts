import { describe, expect, it } from 'vitest';
import {
    formatInvalidationOccurredAt,
    formatInvalidationReason,
} from '@/Utils/format/invalidationReason';

const baseEntity = {
    type: 'contract',
    id: 42,
    label: 'EA-001-AA · ACM · 14/10/2024 → 15/12/2024',
};

function makeReason(
    type: App.Enums.FiscalDeclaration.InvalidationReasonType,
): App.Data.User.FiscalDeclaration.InvalidationReasonData {
    return {
        type,
        occurredAt: '2026-05-08T14:32:11+00:00',
        actorUserId: 1,
        entity: baseEntity,
        fieldsChanged: ['start_date'],
    };
}

describe('formatInvalidationReason', () => {
    it.each([
        ['contract_created', 'Contrat ajouté'],
        ['contract_updated', 'Contrat modifié'],
        ['contract_deleted', 'Contrat supprimé'],
        ['vfc_created', 'Caractéristiques fiscales ajoutées'],
        ['vfc_updated', 'Caractéristiques fiscales modifiées'],
        ['vfc_deleted', 'Caractéristiques fiscales supprimées'],
        ['unavailability_created', 'Indisponibilité ajoutée'],
        ['unavailability_updated', 'Indisponibilité modifiée'],
        ['unavailability_deleted', 'Indisponibilité supprimée'],
    ] as const)('formate "%s" en français avec label entité', (type, expected) => {
        const reason = makeReason(type);

        expect(formatInvalidationReason(reason)).toBe(`${expected} · ${baseEntity.label}`);
    });

    it('omet le séparateur si aucun label entité', () => {
        const reason = {
            ...makeReason('contract_updated'),
            entity: { type: 'contract', id: 1, label: '' },
        };

        expect(formatInvalidationReason(reason)).toBe('Contrat modifié');
    });

    it('reformate les dates ISO YYYY-MM-DD éventuelles du label en DD/MM/YYYY', () => {
        const reason = {
            ...makeReason('contract_created'),
            entity: {
                type: 'contract',
                id: 44,
                label: 'Contrat #44 · véhicule 4 · 2024-07-08 → 2024-07-26',
            },
        };

        expect(formatInvalidationReason(reason)).toBe(
            'Contrat ajouté · Contrat #44 · véhicule 4 · 08/07/2024 → 26/07/2024',
        );
    });
});

describe('formatInvalidationOccurredAt', () => {
    it('formate la date ISO en format français lisible', () => {
        const formatted = formatInvalidationOccurredAt('2026-05-08T14:32:11+00:00');

        expect(formatted).toMatch(/2026/);
    });
});
