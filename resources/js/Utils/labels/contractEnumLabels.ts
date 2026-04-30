/**
 * Maps de traduction FR pour les enums du domaine Contract.
 *
 * Cf. `Utils/labels/vehicleEnumLabels.ts` pour la convention :
 * `Record<EnumValue, string>` force l'exhaustivité TS.
 */

import type { BadgeTone } from '@/types/ui';

export const contractTypeLabel: Record<App.Enums.Contract.ContractType, string> = {
    lcd: 'Location de courte durée (LCD)',
    lld: 'Location de longue durée (LLD)',
    mise_a_disposition_assimilee: 'Mise à disposition assimilée',
};

/**
 * Libellés courts pour badges compacts dans les tableaux. Le libellé
 * long {@see contractTypeLabel} reste utilisé en page Show et formulaires.
 */
export const contractTypeShortLabel: Record<App.Enums.Contract.ContractType, string> = {
    lcd: 'LCD',
    lld: 'LLD',
    mise_a_disposition_assimilee: 'MAD',
};

/**
 * Tone Badge associé à chaque type de contrat. LCD ambre (court terme,
 * dynamique), LLD bleu (long terme, stable), MAD slate (neutre).
 */
export const contractTypeBadgeTone: Record<App.Enums.Contract.ContractType, BadgeTone> = {
    lcd: 'amber',
    lld: 'blue',
    mise_a_disposition_assimilee: 'slate',
};
