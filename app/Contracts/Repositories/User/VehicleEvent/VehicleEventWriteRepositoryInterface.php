<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\VehicleEvent;

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
     */
    public function create(array $attributes): VehicleEvent;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(int $id, array $attributes): VehicleEvent;

    public function softDelete(int $id): void;
}
