<?php

declare(strict_types=1);

namespace App\Actions\Driver;

use App\Contracts\Repositories\User\Driver\DriverReadRepositoryInterface;
use App\Contracts\Repositories\User\Driver\DriverWriteRepositoryInterface;
use App\Data\User\Driver\UpdateDriverCompanyMembershipData;
use App\Exceptions\Driver\DriverMembershipNotFoundException;
use App\Exceptions\Driver\MembershipChronologyException;
use Carbon\CarbonImmutable;

/**
 * Edits the attributes of an existing Driver↔Company membership.
 *
 * Scope:
 *  - `joined_at` (required): new join date.
 *  - `leftAt` (optional):
 *      - `null` (absent OR explicit) reactivates the membership
 *        (clears `left_at`).
 *      - non-null updates the leave date.
 *
 * Enforces chronology: if `leftAt` is set, `joined_at <= left_at`.
 *
 * Distinct from {@see LeaveDriverCompanyMembershipAction}: this Action
 * handles post-facto corrections and reactivation only. The first
 * setting of `left_at` goes through the dedicated leave workflow
 * because it must resolve future contracts.
 */
final readonly class UpdateDriverCompanyMembershipAction
{
    public function __construct(
        private DriverReadRepositoryInterface $driverReadRepo,
        private DriverWriteRepositoryInterface $driverWriteRepo,
    ) {}

    public function execute(int $pivotId, UpdateDriverCompanyMembershipData $data): void
    {
        $pivot = $this->driverReadRepo->findMembershipById($pivotId);
        if ($pivot === null) {
            throw DriverMembershipNotFoundException::forPivotId($pivotId);
        }

        $newJoinedAt = CarbonImmutable::parse($data->joinedAt);
        $newLeftAt = $data->leftAt !== null ? CarbonImmutable::parse($data->leftAt) : null;

        if ($newLeftAt !== null && $newJoinedAt->greaterThan($newLeftAt)) {
            throw MembershipChronologyException::joinedAtAfterLeftAt(
                $newJoinedAt->toDateString(),
                $newLeftAt->toDateString(),
            );
        }

        $this->driverWriteRepo->updateMembership($pivotId, $newJoinedAt, $newLeftAt);
    }
}
