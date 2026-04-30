import type { VehicleFormShape } from '@/pages/User/Vehicles/Create/forms';

/**
 * Shape du formulaire d'édition véhicule.
 *
 * Edit ne sert qu'aux **changements réels** du véhicule dans le temps :
 * il crée systématiquement une nouvelle ligne d'historique fiscal. Les
 * corrections de saisie sur une VFC existante passent exclusivement
 * par la modale Historique de la page véhicule (cf. `VfcEditModal.vue`).
 *
 * Étend le shape de création avec 3 champs spécifiques au flux Edit :
 *   - `effective_from`      : date d'effet de la nouvelle version
 *   - `change_reason`       : motif du changement
 *   - `change_note`         : note libre (requis si motif = other_change)
 */
export type VehicleEditFormShape = VehicleFormShape & {
    effective_from: string;
    change_reason: App.Enums.Vehicle.FiscalCharacteristicsChangeReason | '';
    change_note: string;
};
