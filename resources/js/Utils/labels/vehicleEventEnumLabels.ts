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
    other: 'Autre',
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
    other: 'Autre',
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
