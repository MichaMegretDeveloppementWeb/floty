<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\VehicleEvent;

use App\Enums\VehicleEvent\VehicleEventSystemKind;
use App\Models\VehicleEvent;

/**
 * Writes on the VehicleEvent domain.
 *
 * Pure repository: no business decision (computing `has_fiscal_impact`
 * via the enum, validation, etc.) · that is the role of the Actions.
 */
interface VehicleEventWriteRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $categories  Composed category list, persisted as child rows.
     */
    public function create(array $attributes, array $categories = []): VehicleEvent;

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $categories  Replaces the event's category rows.
     */
    public function update(int $id, array $attributes, array $categories = []): VehicleEvent;

    public function softDelete(int $id): void;

    /**
     * Soft-delete the vehicle's system-generated events of the given kind
     * (lifecycle markers). Used to remove the exit event on reactivation and
     * to re-sync the acquisition event.
     */
    public function deleteSystemEventsForVehicle(int $vehicleId, VehicleEventSystemKind $kind): void;
}
