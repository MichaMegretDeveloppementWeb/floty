/**
 * French label maps for the VehicleEvent domain enums.
 *
 * `vehicleEventTypeLabel` is the SINGLE source of truth for the displayed type
 * label: the same wording is shown in the form select, the timeline, the global
 * list and the detail page (titre = type). Labels are concise but explicit; the
 * enum values (code) and the fiscal rules are untouched, only the display
 * changes. A custom `other` event shows its free title instead.
 */

export const vehicleEventTypeLabel: Record<App.Enums.VehicleEvent.VehicleEventType, string> = {
    accident_no_circulation: 'Sinistre avec interdiction de circuler',
    pound_public: 'Fourrière demande publique',
    ci_suspension: 'Suspension du certificat d\'immatriculation',
    maintenance: 'Maintenance / entretien',
    accident_repair: 'Sinistre avec réparation simple',
    pound_private: 'Fourrière demande privée',
    theft: 'Vol',
    other: 'Événement personnalisé',
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
 * `VehicleEventType::defaultCategory()`). The category is a real classification
 * family, NEVER a copy of the type title: « Sinistre » groups the accidents and
 * the theft, « Administratif » the pounds and the registration suspension,
 * « Suivi véhicule » the maintenance / cleaning (and the regulatory controls,
 * which add « Contrôle réglementaire » on top). `other` has no default.
 */
const vehicleEventTypeDefaultCategory: Partial<Record<App.Enums.VehicleEvent.VehicleEventType, string>> = {
    accident_no_circulation: 'Sinistre',
    accident_repair: 'Sinistre',
    theft: 'Sinistre',
    pound_public: 'Administratif',
    pound_private: 'Administratif',
    ci_suspension: 'Administratif',
    maintenance: 'Suivi véhicule',
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
    'Sinistre',
    'Administratif',
    'Suivi véhicule',
    'Contrôle réglementaire',
    'Cycle de vie',
];

type VehicleEventIdentity = {
    type: App.Enums.VehicleEvent.VehicleEventType;
    title: string | null;
};

/**
 * Display name for an event (list, timeline, detail): the free title for a
 * custom event, the canonical type label otherwise (type = titre).
 */
export function vehicleEventDisplayTitle(event: VehicleEventIdentity): string {
    return event.type === 'other' && event.title !== null && event.title !== ''
        ? event.title
        : vehicleEventTypeLabel[event.type];
}
