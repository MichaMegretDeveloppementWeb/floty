/**
 * Maps de traduction FR pour les enums du domaine Unavailability.
 *
 * Cf. `Utils/labels/vehicleEnumLabels.ts` pour la convention :
 * `Record<EnumValue, string>` force l'exhaustivité TS.
 */

export const unavailabilityTypeLabel: Record<App.Enums.Unavailability.UnavailabilityType, string> = {
    maintenance: 'Maintenance',
    technical_inspection: 'Contrôle technique',
    accident: 'Accident',
    pound: 'Fourrière',
    other: 'Autre',
};
