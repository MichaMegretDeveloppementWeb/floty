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
 * Édite les attributs d'une membership Driver↔Company existante.
 *
 * Scope V1 (chantier B) : seul `joined_at` est modifiable. Vérifie la
 * cohérence chronologique avec `left_at` posé sur la membership cible
 * (rejet si `joined_at > left_at`).
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

        // Cohérence chronologique : si la membership a déjà une date de
        // sortie posée, la nouvelle date d'entrée doit lui être <=.
        if ($pivot->left_at !== null && $newJoinedAt->greaterThan($pivot->left_at)) {
            throw MembershipChronologyException::joinedAtAfterLeftAt(
                $newJoinedAt->toDateString(),
                $pivot->left_at->toDateString(),
            );
        }

        $this->driverWriteRepo->updateMembershipJoinedAt($pivotId, $newJoinedAt);
    }
}
