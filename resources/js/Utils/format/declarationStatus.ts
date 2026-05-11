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
 * Tone du badge global d'une ligne (Phase 11 D5.8.5, enrichi pour
 * distinguer S6 « Générée · obsolète » d'une S7 « Régénération en
 * cours » qui sont visuellement très différents : le premier exige
 * une action de l'utilisateur (régénérer), le second indique qu'une
 * régénération est déjà engagée mais pas finalisée.
 *
 * Priorité de résolution :
 *   1. `hasRegenerationInProgress = true` ⇒ « Régénération en cours »
 *      (orange) : un Draft chaîné existe, l'utilisateur l'a déjà
 *      initiée mais pas encore générée.
 *   2. `isObsolete = true` ⇒ « Générée · obsolète » (rouge) :
 *      version périmée sans régénération démarrée.
 *   3. Statut sous-jacent (`draft`, `deferred`, `generated`).
 */
export function badgeForDeclaration(
    status: Status,
    isObsolete: boolean,
    hasRegenerationInProgress?: boolean | null,
): DeclarationStatusBadge {
    if (hasRegenerationInProgress === true) {
        return { label: 'Régénération en cours', tone: 'amber' };
    }

    if (isObsolete) {
        return { label: 'Générée · obsolète', tone: 'rose' };
    }

    return formatDeclarationStatus(status);
}
