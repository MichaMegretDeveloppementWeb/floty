<?php

declare(strict_types=1);

namespace App\Actions\Unavailability;

use App\Contracts\Repositories\User\Unavailability\UnavailabilityWriteRepositoryInterface;

/**
 * Soft-delete d'une indisponibilité véhicule.
 *
 * Action triviale aujourd'hui (passe-plat) — formalisée pour
 * cohérence du pattern et pour permettre l'ajout d'événements /
 * audit / notifications dans le futur sans modifier le controller.
 */
final readonly class DeleteUnavailabilityAction
{
    public function __construct(
        private UnavailabilityWriteRepositoryInterface $repository,
    ) {}

    public function execute(int $id): void
    {
        $this->repository->softDelete($id);
    }
}
