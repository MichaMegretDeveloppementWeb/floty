<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Driver;

use App\Models\Driver;
use Carbon\CarbonInterface;

/**
 * Écritures Driver - slim conforme ADR-0013.
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
     * Crée une membership Driver↔Company (insertion dans la pivot).
     */
    public function attachCompany(int $driverId, int $companyId, CarbonInterface $joinedAt): void;

    /**
     * Pose `left_at` sur la membership donnée.
     */
    public function setLeaveDate(int $pivotId, CarbonInterface $leftAt): void;

    /**
     * Met à jour `joined_at` sur la membership donnée. Édition de
     * correction (date d'entrée mal saisie, etc.) — chantier B. La
     * cohérence chronologique avec `left_at` est vérifiée côté Action.
     */
    public function updateMembershipJoinedAt(int $pivotId, CarbonInterface $joinedAt): void;

    /**
     * Supprime une membership (uniquement si elle n'a aucun contrat
     * associé - la garde est faite côté Action).
     */
    public function deleteMembership(int $pivotId): void;
}
