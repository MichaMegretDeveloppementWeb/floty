<?php

declare(strict_types=1);

namespace App\Actions\Unavailability;

use App\Contracts\Repositories\User\Assignment\AssignmentReadRepositoryInterface;
use App\Contracts\Repositories\User\Unavailability\UnavailabilityReadRepositoryInterface;
use App\Contracts\Repositories\User\Unavailability\UnavailabilityWriteRepositoryInterface;
use App\Data\User\Unavailability\UpdateUnavailabilityData;
use App\Exceptions\Unavailability\UnavailabilityOverlapsAssignmentsException;
use App\Models\Unavailability;

/**
 * Mise à jour d'une indisponibilité véhicule.
 *
 * Recalcule `has_fiscal_impact` depuis le nouveau type (qui peut
 * avoir changé entre une indispo non-impactante → fourrière ou
 * inverse).
 *
 * **Sécurité métier** : vérifie qu'aucune attribution du véhicule
 * rattaché ne tombe dans la nouvelle plage. La plage actuelle de
 * l'indispo n'a pas besoin d'être exclue car les indispos ne
 * créent pas d'assignments par construction.
 */
final readonly class UpdateUnavailabilityAction
{
    public function __construct(
        private UnavailabilityWriteRepositoryInterface $repository,
        private UnavailabilityReadRepositoryInterface $unavailabilities,
        private AssignmentReadRepositoryInterface $assignments,
    ) {}

    public function execute(int $id, UpdateUnavailabilityData $data): Unavailability
    {
        $existing = $this->unavailabilities->findById($id);

        $conflicts = $this->assignments->findDatesForVehicleInRange(
            $existing->vehicle_id,
            $data->startDate,
            $data->endDate,
        );

        if ($conflicts !== []) {
            throw UnavailabilityOverlapsAssignmentsException::withConflicts($conflicts);
        }

        return $this->repository->update($id, [
            'type' => $data->type,
            'has_fiscal_impact' => $data->type->hasFiscalImpact(),
            'start_date' => $data->startDate,
            'end_date' => $data->endDate,
            'description' => $data->description,
        ]);
    }
}
