<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Control;

use App\Data\User\Control\ControlRecipientData;
use App\Models\VehicleControlOverride;

/**
 * Writes of per-vehicle control overrides / vehicle-specific controls (Chantier B / B2).
 */
interface VehicleControlOverrideWriteRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): VehicleControlOverride;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(VehicleControlOverride $override, array $attributes): VehicleControlOverride;

    public function softDelete(VehicleControlOverride $override): void;

    /**
     * Find-or-create the unique override row for a (vehicle, GLOBAL definition)
     * pair and fill it (restoring it if it was previously soft-deleted, so the
     * unique index is not violated). Used to customise / pause / disable a
     * global control on a vehicle.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function upsertForVehicleDefinition(int $vehicleId, int $definitionId, array $attributes): VehicleControlOverride;

    /**
     * Replace the override's level-2 (vehicle) recipient deltas: `include` rows
     * from `$ownRecipients`, `exclude` rows from `$excludedEmails`.
     *
     * @param  array<int, ControlRecipientData>  $ownRecipients
     * @param  array<int, string>  $excludedEmails
     */
    public function syncRecipients(VehicleControlOverride $override, array $ownRecipients, array $excludedEmails): void;
}
