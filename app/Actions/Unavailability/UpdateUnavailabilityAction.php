<?php

declare(strict_types=1);

namespace App\Actions\Unavailability;

use App\Contracts\Repositories\User\Unavailability\UnavailabilityReadRepositoryInterface;
use App\Contracts\Repositories\User\Unavailability\UnavailabilityWriteRepositoryInterface;
use App\Data\User\Unavailability\UpdateUnavailabilityData;
use App\Exceptions\Unavailability\UnavailabilityOverlapsContractsException;
use App\Models\Unavailability;
use App\Services\Vehicle\VehiclePeriodConflictsService;

/**
 * Mise à jour d'une indisponibilité véhicule.
 *
 * Recalcule `has_fiscal_impact` depuis le nouveau type (qui peut
 * avoir changé entre une indispo non-impactante → fourrière ou
 * inverse).
 *
 * **Sécurité métier** : vérifie qu'aucun contrat actif du véhicule
 * ne chevauche la nouvelle plage via {@see VehiclePeriodConflictsService}.
 * La plage actuelle de l'indispo n'a pas besoin d'être exclue car les
 * indispos ne créent pas de contrats par construction.
 */
final readonly class UpdateUnavailabilityAction
{
    public function __construct(
        private UnavailabilityWriteRepositoryInterface $repository,
        private UnavailabilityReadRepositoryInterface $unavailabilities,
        private VehiclePeriodConflictsService $conflicts,
    ) {}

    public function execute(int $id, UpdateUnavailabilityData $data): Unavailability
    {
        $existing = $this->unavailabilities->findById($id);

        $conflicts = $this->conflicts->expandConflictingDatesForPeriod(
            $existing->vehicle_id,
            $data->startDate,
            $data->endDate,
        );

        if ($conflicts !== []) {
            throw UnavailabilityOverlapsContractsException::withConflicts($conflicts);
        }

        return $this->repository->update($id, [
            'type' => $data->type,
            'has_fiscal_impact' => $data->type->isFiscallyReductive(),
            'start_date' => $data->startDate,
            'end_date' => $data->endDate,
            'description' => $data->description,
        ]);
    }
}
