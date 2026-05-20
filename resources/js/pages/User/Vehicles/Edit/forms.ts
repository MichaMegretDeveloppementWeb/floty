import type { VehicleFormShape } from '@/pages/User/Vehicles/Create/forms';

/**
 * Shape of the vehicle edit form. The Edit flow handles real-world
 * changes only and always creates a new fiscal-history row; typos on
 * an existing VFC are corrected from the history modal
 * (`VfcEditModal.vue`).
 *
 * Extends the create shape with three Edit-specific fields:
 *   - `effective_from` : effective date of the new version
 *   - `change_reason`  : reason for the change
 *   - `change_note`    : free text (required when reason = other_change)
 */
export type VehicleEditFormShape = VehicleFormShape & {
    effective_from: string;
    change_reason: App.Enums.Vehicle.FiscalCharacteristicsChangeReason | '';
    change_note: string;
};
