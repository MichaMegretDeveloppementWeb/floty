/**
 * French label maps for the VehicleEvent domain enums.
 *
 * Labels embed the regulatory precision in plain text (ADR-0016 § 9
 * rev. 1.1) so the user does not need an extra disambiguation step
 * ("public" vs "private", "ban" vs "simple repair", etc.).
 */

export const vehicleEventTypeLabel: Record<App.Enums.VehicleEvent.VehicleEventType, string> = {
    accident_no_circulation: 'Sinistre - interdiction de circuler prononcée par les autorités',
    pound_public: 'Fourrière à la demande des pouvoirs publics',
    ci_suspension: 'Suspension du certificat d\'immatriculation',
    maintenance: 'Maintenance / entretien',
    technical_inspection: 'Contrôle technique',
    accident_repair: 'Sinistre - réparation simple (sans interdiction de circuler)',
    pound_private: 'Fourrière à la demande d\'un privé (réquisition, autre)',
    theft: 'Vol (sans certificat de destruction délivré)',
    other: 'Événement personnalisé',
};

/**
 * Short label for dense contexts (timeline, heatmap cell legend) where
 * the long version would not fit.
 */
export const vehicleEventTypeShortLabel: Record<App.Enums.VehicleEvent.VehicleEventType, string> = {
    accident_no_circulation: 'Interdiction de circuler',
    pound_public: 'Fourrière publique',
    ci_suspension: 'Suspension CI',
    maintenance: 'Maintenance',
    technical_inspection: 'Contrôle technique',
    accident_repair: 'Sinistre / réparation',
    pound_private: 'Fourrière privée',
    theft: 'Vol',
    other: 'Personnalisé',
};

/**
 * True iff the type reduces the numerator of the fiscal proration
 * (R-2024-008). Must stay aligned with PHP
 * `VehicleEventType::isFiscallyReductive()`.
 */
const REDUCTIVE_TYPES: ReadonlyArray<App.Enums.VehicleEvent.VehicleEventType> = [
    'accident_no_circulation',
    'pound_public',
    'ci_suspension',
];

export function isVehicleEventFiscallyReductive(
    type: App.Enums.VehicleEvent.VehicleEventType,
): boolean {
    return REDUCTIVE_TYPES.includes(type);
}

/**
 * Category shown in the events list / timeline. Auto-derived for known
 * types; for the custom "other" type the user-supplied category is used
 * instead (this entry is only a fallback when none was provided).
 */
export const vehicleEventTypeCategory: Record<App.Enums.VehicleEvent.VehicleEventType, string> = {
    accident_no_circulation: 'Accident',
    accident_repair: 'Accident',
    pound_public: 'Fourrière',
    pound_private: 'Fourrière',
    ci_suspension: 'Administratif',
    maintenance: 'Entretien',
    technical_inspection: 'Contrôle réglementaire',
    theft: 'Vol',
    other: 'Personnalisé',
};

/** Distinct known categories, offered as suggestions on the custom-type form. */
export const vehicleEventCategorySuggestions: ReadonlyArray<string> = [
    'Accident',
    'Fourrière',
    'Administratif',
    'Entretien',
    'Contrôle réglementaire',
    'Vol',
];

type VehicleEventIdentity = {
    type: App.Enums.VehicleEvent.VehicleEventType;
    title: string | null;
    category: string | null;
};

/**
 * Display name for compact contexts (events list, timeline): the free title
 * for a custom event, the short enum label otherwise.
 */
export function vehicleEventDisplayTitle(event: VehicleEventIdentity): string {
    return event.type === 'other' && event.title !== null && event.title !== ''
        ? event.title
        : vehicleEventTypeShortLabel[event.type];
}

/** Display category: the free category for a custom event, the mapped one otherwise. */
export function vehicleEventDisplayCategory(event: VehicleEventIdentity): string {
    return event.type === 'other' && event.category !== null && event.category !== ''
        ? event.category
        : vehicleEventTypeCategory[event.type];
}
