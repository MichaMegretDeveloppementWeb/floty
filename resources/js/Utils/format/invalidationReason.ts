/**
 * Formatting helpers for typed obsolescence reasons of a fiscal
 * declaration (ADR-0015 § D9).
 *
 * Source: `App.Data.User.FiscalDeclaration.InvalidationReasonData`.
 * The output composes an action verb + the touched entity label to
 * produce a self-contained sentence for the UI.
 */
type Reason = App.Data.User.FiscalDeclaration.InvalidationReasonData;
type Type = App.Enums.FiscalDeclaration.InvalidationReasonType;

const VERB_BY_TYPE: Record<Type, string> = {
    contract_created: 'Contrat ajouté',
    contract_updated: 'Contrat modifié',
    contract_deleted: 'Contrat supprimé',
    vfc_created: 'Caractéristiques fiscales ajoutées',
    vfc_updated: 'Caractéristiques fiscales modifiées',
    vfc_deleted: 'Caractéristiques fiscales supprimées',
    unavailability_created: 'Indisponibilité ajoutée',
    unavailability_updated: 'Indisponibilité modifiée',
    unavailability_deleted: 'Indisponibilité supprimée',
    vehicle_updated: 'Véhicule modifié',
    voluntary_modification: 'Modification volontaire',
};

export function formatInvalidationReason(reason: Reason): string {
    const verb = VERB_BY_TYPE[reason.type] ?? reason.type;
    const label = reason.entity?.label ?? '';

    return label ? `${verb} · ${frenchifyIsoDates(label)}` : verb;
}

/**
 * Rewrite ISO `YYYY-MM-DD` dates embedded in a string as FR
 * `DD/MM/YYYY`. Used to fix legacy invalidation labels persisted before
 * formatting was moved into `DeclarationInvalidationDetector`.
 */
function frenchifyIsoDates(label: string): string {
    return label.replace(/\b(\d{4})-(\d{2})-(\d{2})\b/g, '$3/$2/$1');
}

export function formatInvalidationOccurredAt(occurredAt: string): string {
    return new Date(occurredAt).toLocaleString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
