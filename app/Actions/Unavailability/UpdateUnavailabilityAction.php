<?php

declare(strict_types=1);

namespace App\Actions\Unavailability;

use App\Contracts\Repositories\User\Unavailability\UnavailabilityWriteRepositoryInterface;
use App\Data\User\Unavailability\UpdateUnavailabilityData;
use App\Models\Unavailability;

/**
 * Mise à jour d'une indisponibilité véhicule.
 *
 * Recalcule `has_fiscal_impact` depuis le nouveau type (qui peut
 * avoir changé entre une indispo non-impactante → fourrière ou
 * inverse).
 *
 * **Cohabitation indispo↔contrat (ADR-0019)** : aucune contrainte
 * d'overlap avec les contrats actifs du véhicule lors d'une édition
 * de plage. Symétrique de {@see CreateUnavailabilityAction}.
 */
final readonly class UpdateUnavailabilityAction
{
    public function __construct(
        private UnavailabilityWriteRepositoryInterface $repository,
    ) {}

    public function execute(int $id, UpdateUnavailabilityData $data): Unavailability
    {
        return $this->repository->update($id, [
            'type' => $data->type,
            'has_fiscal_impact' => $data->type->isFiscallyReductive(),
            'start_date' => $data->startDate,
            'end_date' => $data->endDate,
            'description' => $data->description,
        ]);
    }
}
