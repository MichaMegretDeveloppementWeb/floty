<?php

declare(strict_types=1);

namespace App\Actions\Unavailability;

use App\Contracts\Repositories\User\Assignment\AssignmentReadRepositoryInterface;
use App\Contracts\Repositories\User\Unavailability\UnavailabilityWriteRepositoryInterface;
use App\Data\User\Unavailability\StoreUnavailabilityData;
use App\Enums\Unavailability\UnavailabilityType;
use App\Exceptions\Unavailability\UnavailabilityOverlapsAssignmentsException;
use App\Models\Unavailability;

/**
 * Création d'une indisponibilité véhicule.
 *
 * **Décision métier portée ici** : `has_fiscal_impact` est dérivé de
 * `type` via {@see UnavailabilityType::hasFiscalImpact()}
 * — le payload utilisateur ne le porte jamais (cf. CHECK SQL en base
 * qui garantit la cohérence).
 *
 * **Sécurité métier** : vérifie qu'aucune attribution du véhicule ne
 * tombe dans la plage demandée. L'UI bloque déjà la sélection mais
 * cette vérification couvre les POST hors UI et les races (un autre
 * user crée une attribution pendant que le formulaire est ouvert).
 */
final readonly class CreateUnavailabilityAction
{
    public function __construct(
        private UnavailabilityWriteRepositoryInterface $repository,
        private AssignmentReadRepositoryInterface $assignments,
    ) {}

    public function execute(StoreUnavailabilityData $data): Unavailability
    {
        $conflicts = $this->assignments->findDatesForVehicleInRange(
            $data->vehicleId,
            $data->startDate,
            $data->endDate,
        );

        if ($conflicts !== []) {
            throw UnavailabilityOverlapsAssignmentsException::withConflicts($conflicts);
        }

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
