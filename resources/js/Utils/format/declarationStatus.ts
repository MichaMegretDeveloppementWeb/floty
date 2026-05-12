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
 *
 * **Conservé pour compatibilité** avec la page Show qui veut un pill
 * unique combinant statut + obsolescence. La page Index Déclarations
 * utilise `pillForIndexRow` + `subMentionForRow` depuis Phase 13
 * D5.10.F pour découpler ces informations (colonne Obsolète dédiée +
 * sous-mention contextuelle).
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

type DeclarationRow = App.Data.User.FiscalDeclaration.DeclarationListItemData;

export type IndexSubMention = { text: string; targetDeclarationId: number } | null;

/**
 * Pill principal de la colonne « Statut » sur l'Index Déclarations
 * (Phase 13 D5.10.F). Refonte de `badgeForDeclaration` qui ne porte
 * **plus** l'obsolescence (désormais dans sa propre colonne). Le seul
 * enrichissement maintenu dans le pill est le cas « Brouillon ·
 * régénération » qui distingue visuellement un draft initial d'un
 * draft de régénération chaîné à un predecessor.
 */
export function pillForIndexRow(row: DeclarationRow): DeclarationStatusBadge {
    if (row.status === 'draft' && row.predecessorReference !== null) {
        return { label: 'Brouillon · régénération', tone: 'amber' };
    }

    return formatDeclarationStatus(row.status);
}

/**
 * Sous-mention contextuelle sous le pill `Statut` sur l'Index
 * Déclarations (Phase 13 D5.10.F). Décrit la position de la ligne
 * dans sa chaîne d'obsolescence, avec une cible cliquable pour
 * naviguer vers la déclaration référencée.
 *
 * Priorité de résolution :
 *   1. Ligne obsolète avec un successor · décrit le successor
 *      (« Régénération en cours · DECL-XXX » si Draft, « Remplacée
 *      par DECL-XXX » sinon).
 *   2. Ligne ayant un predecessor · décrit le predecessor
 *      (« Remplace DECL-XXX », cliquable vers le predecessor).
 *   3. Sinon `null` (pas de sous-mention).
 */
export function subMentionForRow(row: DeclarationRow): IndexSubMention {
    if (row.isObsolete && row.supersededById !== null && row.successorStatus !== null) {
        // Phase 13 D5.10.H · `internalLabel` est calculé backend ·
        // « DECL-XXX » si générée, sinon « Brouillon #N ». Cohérent
        // partout dans l'UI.
        const successorLabel = row.successorReference
            ?? `Brouillon #${row.supersededById}`;

        const text = row.successorStatus === 'draft'
            ? `Régénération en cours · ${successorLabel}`
            : `Remplacée par ${successorLabel}`;

        return { text, targetDeclarationId: row.supersededById };
    }

    if (row.predecessorId !== null) {
        const predecessorLabel = row.predecessorReference ?? `Brouillon #${row.predecessorId}`;

        return {
            text: `Remplace ${predecessorLabel}`,
            targetDeclarationId: row.predecessorId,
        };
    }

    return null;
}
