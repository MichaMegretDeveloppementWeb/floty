<?php

declare(strict_types=1);

namespace App\Actions\Unavailability;

use App\Contracts\Repositories\User\Unavailability\UnavailabilityWriteRepositoryInterface;
use App\Data\User\Unavailability\StoreUnavailabilityData;
use App\Enums\Unavailability\UnavailabilityType;
use App\Models\Unavailability;

/**
 * Création d'une indisponibilité véhicule.
 *
 * **Décision métier portée ici** : `has_fiscal_impact` est dérivé de
 * `type` via {@see UnavailabilityType::hasFiscalImpact()}
 * — le payload utilisateur ne le porte jamais (cf. CHECK SQL en base
 * qui garantit la cohérence).
 */
final readonly class CreateUnavailabilityAction
{
    public function __construct(
        private UnavailabilityWriteRepositoryInterface $repository,
    ) {}

    public function execute(StoreUnavailabilityData $data): Unavailability
    {
        return $this->repository->create([
            'vehicle_id' => $data->vehicleId,
            'type' => $data->type,
            'has_fiscal_impact' => $data->type->hasFiscalImpact(),
            'start_date' => $data->startDate,
            'end_date' => $data->endDate,
            'description' => $data->description,
        ]);
    }
}
