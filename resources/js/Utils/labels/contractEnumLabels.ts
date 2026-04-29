/**
 * Maps de traduction FR pour les enums du domaine Contract.
 *
 * Cf. `Utils/labels/vehicleEnumLabels.ts` pour la convention :
 * `Record<EnumValue, string>` force l'exhaustivité TS.
 */

export const contractTypeLabel: Record<App.Enums.Contract.ContractType, string> = {
    lcd: 'Location de courte durée (LCD)',
    lld: 'Location de longue durée (LLD)',
    mise_a_disposition_assimilee: 'Mise à disposition assimilée',
};
