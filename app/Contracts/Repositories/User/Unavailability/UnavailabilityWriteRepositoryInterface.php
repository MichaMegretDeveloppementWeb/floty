<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Unavailability;

use App\Models\Unavailability;

/**
 * Writes on the Unavailability domain.
 *
 * Pure repository: no business decision (computing `has_fiscal_impact`
 * via the enum, validation, etc.) · that is the role of the Actions.
 */
interface UnavailabilityWriteRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Unavailability;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(int $id, array $attributes): Unavailability;

    public function softDelete(int $id): void;
}
