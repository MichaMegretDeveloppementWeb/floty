import type { VehicleFormShape } from '@/pages/User/Vehicles/Create/forms';

/**
 * Shape du formulaire d'édition véhicule. Étend le shape de création
 * avec 4 champs spécifiques au flux Edit :
 *   - `fiscal_change_mode`  : 'correction' | 'new_version'
 *   - `effective_from`      : date d'effet (requis si new_version)
 *   - `change_reason`       : motif de changement (requis si new_version)
 *   - `change_note`         : note libre (requis si motif = other_change)
 */
export type VehicleEditFormShape = VehicleFormShape & {
    fiscal_change_mode: App.Enums.Vehicle.FiscalChangeMode;
    effective_from: string;
    change_reason: App.Enums.Vehicle.FiscalCharacteristicsChangeReason | '';
    change_note: string;
};
