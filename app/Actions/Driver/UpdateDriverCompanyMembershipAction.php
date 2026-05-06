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
 * Scope (chantier B + B-bis) :
 *  - `joined_at` requis : nouvelle date d'entrée.
 *  - `leftAt` optionnel :
 *      - `null` (clé absente OU `null` explicite) → réactive la
 *        membership (efface `left_at` en base, ré-bascule en « Actif »).
 *      - non null → met à jour la date de sortie.
 *
 * Vérifie la cohérence chronologique : si `leftAt` est posé, alors
 * `joined_at <= left_at`. Sinon, throw {@see MembershipChronologyException}.
 *
 * **Distinction avec `LeaveDriverCompanyMembershipAction`** : cet Action
 * gère les *corrections post-facto* + la réactivation. Le workflow
 * Sortir dédié (`Leave...Action`) reste responsable de la *première*
 * pose de `left_at`, car il orchestre la résolution des contrats à
 * venir (Q6).
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

        // Cohérence chronologique : si on pose un `left_at`, il doit
        // être >= au nouveau `joined_at`. Si on réactive (`null`), pas
        // de check à faire.
        if ($newLeftAt !== null && $newJoinedAt->greaterThan($newLeftAt)) {
            throw MembershipChronologyException::joinedAtAfterLeftAt(
                $newJoinedAt->toDateString(),
                $newLeftAt->toDateString(),
            );
        }

        $this->driverWriteRepo->updateMembership($pivotId, $newJoinedAt, $newLeftAt);
    }
}
