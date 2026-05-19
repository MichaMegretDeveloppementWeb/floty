/**
 * French label maps for the Contract domain enums.
 *
 * `Record<EnumValue, string>` forces exhaustiveness at the TS level.
 */

import type { BadgeTone } from '@/types/ui';

export const contractTypeLabel: Record<App.Enums.Contract.ContractType, string> = {
    lcd: 'Location de courte durée (LCD)',
    lld: 'Location de longue durée (LLD)',
    mise_a_disposition_assimilee: 'Mise à disposition assimilée',
};

/**
 * Short label for compact badges in tables. The long label
 * {@see contractTypeLabel} stays in Show pages and forms.
 */
export const contractTypeShortLabel: Record<App.Enums.Contract.ContractType, string> = {
    lcd: 'LCD',
    lld: 'LLD',
    mise_a_disposition_assimilee: 'MAD',
};

/**
 * Badge tone per contract type. LCD amber (short term, dynamic), LLD
 * blue (long term, stable), MAD slate (neutral).
 */
export const contractTypeBadgeTone: Record<App.Enums.Contract.ContractType, BadgeTone> = {
    lcd: 'amber',
    lld: 'blue',
    mise_a_disposition_assimilee: 'slate',
};
