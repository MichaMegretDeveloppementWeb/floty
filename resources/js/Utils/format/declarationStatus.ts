/**
 * Helpers de formatage du statut + obsolescence d'une déclaration
 * fiscale (Phase 11 D4).
 */
import type { StatusTone } from '@/types/ui';

type Status = App.Enums.FiscalDeclaration.FiscalDeclarationStatus;

export type DeclarationStatusBadge = {
    label: string;
    tone: StatusTone;
};

const STATUS_LABELS: Record<Status, string> = {
    draft: 'Brouillon',
    deferred: 'Mise de côté',
    generated: 'Générée',
};

const STATUS_TONES: Record<Status, StatusTone> = {
    draft: 'slate',
    deferred: 'amber',
    generated: 'emerald',
};

export function formatDeclarationStatus(status: Status): DeclarationStatusBadge {
    return {
        label: STATUS_LABELS[status],
        tone: STATUS_TONES[status],
    };
}

/**
 * Tone du badge global d'une ligne : si obsolète, on prend tone
 * `rose` indépendamment du statut sous-jacent (le flag d'obsolescence
 * est plus critique pour l'utilisateur que la transition d'état).
 */
export function badgeForDeclaration(
    status: Status,
    isObsolete: boolean,
): DeclarationStatusBadge {
    if (isObsolete) {
        return { label: 'Obsolète', tone: 'rose' };
    }

    return formatDeclarationStatus(status);
}
