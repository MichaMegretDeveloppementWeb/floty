<?php

declare(strict_types=1);

namespace App\Actions\Unavailability;

use App\Contracts\Repositories\User\Unavailability\UnavailabilityWriteRepositoryInterface;
use App\Data\User\Unavailability\StoreUnavailabilityData;
use App\Enums\Unavailability\UnavailabilityType;
use App\Exceptions\Unavailability\UnavailabilityOverlapsContractsException;
use App\Models\Contract;
use App\Models\Unavailability;
use Carbon\CarbonImmutable;

/**
 * Création d'une indisponibilité véhicule.
 *
 * **Décision métier portée ici** : `has_fiscal_impact` est dérivé de
 * `type` via {@see UnavailabilityType::hasFiscalImpact()}
 * — le payload utilisateur ne le porte jamais (cf. CHECK SQL en base
 * qui garantit la cohérence).
 *
 * **Sécurité métier** : vérifie qu'aucun contrat actif du véhicule ne
 * chevauche la plage demandée. L'UI bloque déjà la sélection mais cette
 * vérification couvre les POST hors UI et les races (un autre user
 * crée un contrat pendant que le formulaire est ouvert).
 */
final readonly class CreateUnavailabilityAction
{
    public function __construct(
        private UnavailabilityWriteRepositoryInterface $repository,
    ) {}

    public function execute(StoreUnavailabilityData $data): Unavailability
    {
        $conflicts = $this->collectOverlappingDates(
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
