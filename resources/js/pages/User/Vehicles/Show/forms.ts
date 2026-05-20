import type { FiscalCharacteristicsFieldsShape } from '@/pages/User/Vehicles/Create/forms';

/**
 * Shape of the standalone VFC edit form from the history modal.
 * Extends the fiscal fields with the `effective_from`/`effective_to`
 * bounds and the change reason/note. `effective_to = null` marks the
 * current version; the pre-submit transform converts `''` → `null`.
 */
export type VfcEditFormShape = FiscalCharacteristicsFieldsShape & {
    effective_from: string;
    effective_to: string;
    change_reason: App.Enums.Vehicle.FiscalCharacteristicsChangeReason | '';
    change_note: string;
};

/**
 * Shape of the VFC deletion form. The only field carried is the
 * strategy used to fill the gap left by the deletion.
 */
export type VfcDeleteFormShape = {
    extension_strategy: App.Enums.Vehicle.FiscalCharacteristicsExtensionStrategy | '';
};
