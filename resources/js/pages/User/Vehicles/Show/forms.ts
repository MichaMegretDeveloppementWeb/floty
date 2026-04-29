import type { FiscalCharacteristicsFieldsShape } from '@/pages/User/Vehicles/Create/forms';

/**
 * Shape du formulaire d'édition d'une VFC isolée depuis la modale
 * Historique. Étend les champs purement fiscaux avec les bornes
 * `effective_from`/`effective_to` et le motif/note de changement.
 *
 * `effective_to` à `null` = version courante. La transformation
 * pre-submit gère la conversion `''` → `null`.
 */
export type VfcEditFormShape = FiscalCharacteristicsFieldsShape & {
    effective_from: string;
    effective_to: string;
    change_reason: App.Enums.Vehicle.FiscalCharacteristicsChangeReason | '';
    change_note: string;
};

/**
 * Shape du formulaire de suppression d'une VFC. Le seul champ porté
 * est la stratégie de comblement du trou laissé par la suppression.
 */
export type VfcDeleteFormShape = {
    extension_strategy: App.Enums.Vehicle.FiscalCharacteristicsExtensionStrategy | '';
};
