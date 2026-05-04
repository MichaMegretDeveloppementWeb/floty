<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Unavailability;

use App\Models\Unavailability;

/**
 * Écritures sur le domaine Unavailability.
 *
 * Repo pur : aucune décision métier (calcul de `has_fiscal_impact`
 * via l'enum, validation, etc.) - c'est le rôle des Actions.
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
