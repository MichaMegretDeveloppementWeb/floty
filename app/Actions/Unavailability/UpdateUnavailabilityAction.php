<?php

declare(strict_types=1);

namespace App\Actions\Unavailability;

use App\Contracts\Repositories\User\Unavailability\UnavailabilityReadRepositoryInterface;
use App\Contracts\Repositories\User\Unavailability\UnavailabilityWriteRepositoryInterface;
use App\Data\User\Unavailability\UpdateUnavailabilityData;
use App\Exceptions\Unavailability\UnavailabilityOverlapsContractsException;
use App\Models\Contract;
use App\Models\Unavailability;
use Carbon\CarbonImmutable;

/**
 * Mise à jour d'une indisponibilité véhicule.
 *
 * Recalcule `has_fiscal_impact` depuis le nouveau type (qui peut
 * avoir changé entre une indispo non-impactante → fourrière ou
 * inverse).
 *
 * **Sécurité métier** : vérifie qu'aucun contrat actif du véhicule
 * ne chevauche la nouvelle plage. La plage actuelle de l'indispo n'a
 * pas besoin d'être exclue car les indispos ne créent pas de contrats
 * par construction.
 */
final readonly class UpdateUnavailabilityAction
{
    public function __construct(
        private UnavailabilityWriteRepositoryInterface $repository,
        private UnavailabilityReadRepositoryInterface $unavailabilities,
    ) {}

    public function execute(int $id, UpdateUnavailabilityData $data): Unavailability
    {
        $existing = $this->unavailabilities->findById($id);

        $conflicts = $this->collectOverlappingDates(
            $existing->vehicle_id,
            $data->startDate,
            $data->endDate,
        );

        if ($conflicts !== []) {
            throw UnavailabilityOverlapsContractsException::withConflicts($conflicts);
        }

        return $this->repository->update($id, [
            'type' => $data->type,
            'has_fiscal_impact' => $data->type->hasFiscalImpact(),
            'start_date' => $data->startDate,
            'end_date' => $data->endDate,
            'description' => $data->description,
        ]);
    }

    /**
     * @return list<string>
     */
    private function collectOverlappingDates(int $vehicleId, string $startDate, string $endDate): array
    {
        $contracts = Contract::query()
            ->where('vehicle_id', $vehicleId)
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate)
            ->get();

        $dates = [];
        foreach ($contracts as $contract) {
            $cursor = $contract->start_date->isAfter($startDate)
                ? $contract->start_date
                : CarbonImmutable::parse($startDate);
            $stop = $contract->end_date->isBefore($endDate)
                ? $contract->end_date
                : CarbonImmutable::parse($endDate);

            while (! $cursor->isAfter($stop)) {
                $dates[$cursor->toDateString()] = true;
                $cursor = $cursor->addDay();
            }
        }
        $list = array_keys($dates);
        sort($list);

        return $list;
    }
}
