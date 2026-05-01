<?php

declare(strict_types=1);

namespace App\Actions\Unavailability;

use App\Contracts\Repositories\User\Unavailability\UnavailabilityWriteRepositoryInterface;
use App\Data\User\Unavailability\StoreUnavailabilityData;
use App\Enums\Unavailability\UnavailabilityType;
use App\Exceptions\Unavailability\UnavailabilityOverlapsContractsException;
use App\Models\Unavailability;
use App\Services\Vehicle\VehiclePeriodConflictsService;

/**
 * Création d'une indisponibilité véhicule.
 *
 * **Décision métier portée ici** : `has_fiscal_impact` est dérivé de
 * `type` via {@see UnavailabilityType::isFiscallyReductive()}
 * — le payload utilisateur ne le porte jamais (cf. CHECK SQL en base
 * qui garantit la cohérence).
 *
 * **Sécurité métier** : vérifie qu'aucun contrat actif du véhicule ne
 * chevauche la plage demandée via {@see VehiclePeriodConflictsService}.
 * L'UI bloque déjà la sélection mais cette vérification couvre les POST
 * hors UI et les races (un autre user crée un contrat pendant que le
 * formulaire est ouvert).
 */
final readonly class CreateUnavailabilityAction
{
    public function __construct(
        private UnavailabilityWriteRepositoryInterface $repository,
        private VehiclePeriodConflictsService $conflicts,
    ) {}

    public function execute(StoreUnavailabilityData $data): Unavailability
    {
        $conflicts = $this->conflicts->expandConflictingDatesForPeriod(
            $data->vehicleId,
            $data->startDate,
            $data->endDate,
        );

        if ($conflicts !== []) {
            throw UnavailabilityOverlapsContractsException::withConflicts($conflicts);
        }

        return $this->repository->create([
            'vehicle_id' => $data->vehicleId,
            'type' => $data->type,
            'has_fiscal_impact' => $data->type->isFiscallyReductive(),
            'start_date' => $data->startDate,
            'end_date' => $data->endDate,
            'description' => $data->description,
        ]);
    }
}
