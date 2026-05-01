/**
 * Maps de traduction FR pour les enums du domaine Unavailability.
 *
 * Cf. `Utils/labels/vehicleEnumLabels.ts` pour la convention :
 * `Record<EnumValue, string>` force l'exhaustivité TS.
 *
 * Les libellés intègrent la précision réglementaire en clair (ADR-0016
 * § 9 rev. 1.1) — l'utilisateur n'a pas besoin d'une étape supplémentaire
 * pour lever les ambiguïtés (« publique » vs « privée »,
 * « interdiction » vs « réparation simple », etc.).
 */

export const unavailabilityTypeLabel: Record<App.Enums.Unavailability.UnavailabilityType, string> = {
    accident_no_circulation: 'Sinistre — interdiction de circuler prononcée par les autorités',
    pound_public: 'Fourrière à la demande des pouvoirs publics',
    ci_suspension: 'Suspension du certificat d\'immatriculation',
    maintenance: 'Maintenance / entretien',
    technical_inspection: 'Contrôle technique',
    accident_repair: 'Sinistre — réparation simple (sans interdiction de circuler)',
    pound_private: 'Fourrière à la demande d\'un privé (réquisition, autre)',
    theft: 'Vol (sans certificat de destruction délivré)',
    other: 'Autre',
};

/**
 * Libellé court pour les contextes denses (timeline, légende cellule
 * heatmap) où la version longue prendrait trop de place.
 */
export const unavailabilityTypeShortLabel: Record<App.Enums.Unavailability.UnavailabilityType, string> = {
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
 * Vrai ssi le type réduit le numérateur du prorata fiscal (R-2024-008).
 * Doit rester aligné avec PHP `UnavailabilityType::isFiscallyReductive()`.
 */
const REDUCTIVE_TYPES: ReadonlyArray<App.Enums.Unavailability.UnavailabilityType> = [
    'accident_no_circulation',
    'pound_public',
    'ci_suspension',
];

export function isUnavailabilityFiscallyReductive(
    type: App.Enums.Unavailability.UnavailabilityType,
): boolean {
    return REDUCTIVE_TYPES.includes(type);
}
