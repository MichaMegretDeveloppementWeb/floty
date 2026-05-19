<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Driver;

use App\Models\Driver;
use Carbon\CarbonInterface;

/**
 * Driver writes · slim per ADR-0013.
 */
interface DriverWriteRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Driver;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Driver $driver, array $attributes): Driver;

    public function softDelete(Driver $driver): void;

    /**
     * Creates a Driver↔Company membership (pivot insert).
     */
    public function attachCompany(int $driverId, int $companyId, CarbonInterface $joinedAt): void;

    /**
     * Sets `left_at` on the given membership.
     */
    public function setLeaveDate(int $pivotId, CarbonInterface $leftAt): void;

    /**
     * Updates the dates of an existing membership (`joined_at` required,
     * `left_at` optional). Allows:
     *   - correcting `joined_at` alone
     *   - editing both dates at once
     *   - reactivation: `leftAt: null` resets `left_at`
     *
     * Chronological consistency (`joined_at <= left_at` when not null)
     * is checked by the Action.
     */
    public function updateMembership(int $pivotId, CarbonInterface $joinedAt, ?CarbonInterface $leftAt): void;

    /**
     * Deletes a membership (only if it has no associated contracts ·
     * the guard is in the Action).
     */
    public function deleteMembership(int $pivotId): void;
}
