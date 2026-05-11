/**
 * Formatage des motifs typés d'obsolescence d'une déclaration fiscale
 * (Phase 11 D4, ADR-0015 § D9).
 *
 * Source : `App.Data.User.FiscalDeclaration.InvalidationReasonData`.
 * Le format inclut le verbe d'action + le label de l'entité touchée
 * pour produire une phrase auto-contenue dans l'UI.
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
};

export function formatInvalidationReason(reason: Reason): string {
    const verb = VERB_BY_TYPE[reason.type] ?? reason.type;
    const label = reason.entity?.label ?? '';

    return label ? `${verb} · ${label}` : verb;
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
