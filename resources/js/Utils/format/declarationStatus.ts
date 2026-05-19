/**
 * Formatting helpers for the status + obsolescence of a fiscal
 * declaration.
 */
import type { StatusTone } from '@/types/ui';

type Status = App.Enums.FiscalDeclaration.FiscalDeclarationStatus;

export type DeclarationStatusBadge = {
    label: string;
    tone: StatusTone;
};

const STATUS_LABELS: Record<Status, string> = {
    draft: 'Brouillon',
    deferred: 'Reportée',
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
 * Global row badge tone, enriched to distinguish "Générée · obsolète"
 * (action required: regenerate) from "Régénération en cours" (a Draft
 * is already chained but not yet generated).
 *
 * Resolution priority:
 *   1. `hasRegenerationInProgress = true` -> "Régénération en cours"
 *      (amber): a chained Draft exists, the user has started but not
 *      yet generated it.
 *   2. `isObsolete = true` AND `status = generated` -> "Générée ·
 *      obsolète" (rose): outdated version with no regeneration
 *      started. Backend no longer applies the obsolete flag to drafts
 *      (`draft` / `deferred`), so this branch only fires for generated
 *      rows. Safety-net behavior if a residual instance still surfaced
 *      with a flagged draft: return the regular status label without
 *      the obsolete mention, which would be semantically wrong on a
 *      draft.
 *   3. Underlying status (`draft`, `deferred`, `generated`).
 *
 * Kept for compatibility with the Show page, which displays a single
 * pill combining status + obsolescence. The Declarations Index uses
 * `pillForIndexRow` + `subMentionForRow` to decouple these (dedicated
 * Obsolete column + contextual sub-mention).
 */
export function badgeForDeclaration(
    status: Status,
    isObsolete: boolean,
    hasRegenerationInProgress?: boolean | null,
): DeclarationStatusBadge {
    if (hasRegenerationInProgress === true) {
        return { label: 'Régénération en cours', tone: 'amber' };
    }

    if (isObsolete && status === 'generated') {
        return { label: 'Générée · obsolète', tone: 'rose' };
    }

    return formatDeclarationStatus(status);
}

type DeclarationRow = App.Data.User.FiscalDeclaration.DeclarationListItemData;

export type IndexSubMention = { text: string; targetDeclarationId: number } | null;

/**
 * Main Status-column pill on the Declarations Index. Companion to
 * `badgeForDeclaration` that no longer carries obsolescence (now in
 * its own column). The only enrichment kept in the pill is the
 * "Brouillon · régénération" case that visually distinguishes an
 * initial draft from a regeneration draft chained to a predecessor.
 */
export function pillForIndexRow(row: DeclarationRow): DeclarationStatusBadge {
    if (row.status === 'draft' && row.predecessorReference !== null) {
        return { label: 'Brouillon · régénération', tone: 'amber' };
    }

    return formatDeclarationStatus(row.status);
}

/**
 * Contextual sub-mention shown beneath the Status pill on the
 * Declarations Index. Describes the row's position in its obsolescence
 * chain with a clickable target to navigate to the referenced
 * declaration.
 *
 * Resolution priority:
 *   1. Obsolete row with a successor: describes the successor
 *      ("Régénération en cours · DECL-XXX" if Draft, "Remplacée par
 *      DECL-XXX" otherwise).
 *   2. Row with a predecessor: describes the predecessor ("Remplace
 *      DECL-XXX", clickable toward the predecessor).
 *   3. Otherwise `null`.
 */
export function subMentionForRow(row: DeclarationRow): IndexSubMention {
    if (row.isObsolete && row.supersededById !== null && row.successorStatus !== null) {
        // `internalLabel` is computed on the backend: "DECL-XXX" if
        // generated, otherwise "Brouillon #N". Consistent across the UI.
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
