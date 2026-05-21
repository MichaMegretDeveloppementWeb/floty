import type { VehicleFormShape } from '@/pages/User/Vehicles/Create/forms';

/**
 * Shape of the vehicle edit form.
 *
 * Two fiscal-write modes coexist:
 *   - `create_new_version = false` (default): fiscal changes update
 *     the current VFC in place, retroactive on its whole effective
 *     period. Suited to typo corrections after vehicle creation.
 *   - `create_new_version = true`: fiscal changes insert a new VFC
 *     row and apply the retroactive cascade. Requires
 *     `effective_from` and `change_reason`.
 *
 * Extends the create shape with four Edit-specific fields:
 *   - `create_new_version` : opt-in checkbox toggle
 *   - `effective_from`     : effective date of the new version
 *   - `change_reason`      : reason for the change
 *   - `change_note`        : free text (required when reason = other_change)
 */
export type VehicleEditFormShape = VehicleFormShape & {
    create_new_version: boolean;
    effective_from: string;
    change_reason: App.Enums.Vehicle.FiscalCharacteristicsChangeReason | '';
    change_note: string;
};
