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
 * Default category prepended to a known-type event (mirror of the PHP
 * `VehicleEventType::defaultCategory()`). Used ONLY by the form to show the
 * locked default chip; the stored categories live on the event itself.
 * `other` has no default (the user supplies all its categories).
 */
const vehicleEventTypeDefaultCategory: Partial<Record<App.Enums.VehicleEvent.VehicleEventType, string>> = {
    accident_no_circulation: 'Accident',
    accident_repair: 'Accident',
    pound_public: 'Fourrière',
    pound_private: 'Fourrière',
    ci_suspension: 'Administratif',
    maintenance: 'Entretien',
    theft: 'Vol',
};

/**
 * Fixed default category(ies) of a type, shown locked in the form (the user
 * adds custom categories on top). Empty for `other`.
 */
export function vehicleEventDefaultCategories(
    type: App.Enums.VehicleEvent.VehicleEventType,
): string[] {
    const def = vehicleEventTypeDefaultCategory[type];

    return def === undefined ? [] : [def];
}

/** Seed suggestions for the category autocomplete (merged with already-used ones). */
export const vehicleEventCategorySuggestions: ReadonlyArray<string> = [
    'Accident',
    'Fourrière',
    'Administratif',
    'Entretien',
    'Contrôle',
    'Vol',
];

type VehicleEventIdentity = {
    type: App.Enums.VehicleEvent.VehicleEventType;
    title: string | null;
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
