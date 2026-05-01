<?php

declare(strict_types=1);

namespace App\Services\Vehicle;

use App\Contracts\Repositories\User\Contract\ContractReadRepositoryInterface;
use Carbon\CarbonImmutable;

/**
 * Service utilitaire qui calcule, pour un véhicule donné et une période
 * `[startDate, endDate]`, la liste exhaustive de dates où un contrat
 * actif chevauche déjà la période.
 *
 * Centralise la logique d'expansion en jours qui était dupliquée mot
 * pour mot entre `CreateUnavailabilityAction` et `UpdateUnavailabilityAction`
 * (anti ADR-0013 et code mort par duplication). Préparé aussi pour le
 * chantier H (UX attribution) : la liste de dates conflictuelles sera
 * exposée au DateRangePicker frontend pour neutraliser la sélection
 * des plages déjà prises.
 *
 * Pure : pas d'effet de bord, pas de transaction, pas de dépendance
 * frontend ni HTTP. Testable unitairement avec un repo mocké.
 */
final readonly class VehiclePeriodConflictsService
{
    public function __construct(
        private ContractReadRepositoryInterface $contracts,
    ) {}

    /**
     * Liste triée et dédoublonnée des dates `Y-m-d` où un contrat actif
     * du véhicule chevauche la période `[startDate, endDate]` inclusive.
     * Liste vide si aucun conflit.
     *
     * Si un contrat déborde la période demandée (commence avant
     * `startDate` ou finit après `endDate`), seules les dates **dans**
     * la période sont incluses. La période demandée fait office de
     * fenêtre de filtrage.
     *
     * @return list<string>
     */
    public function expandConflictingDatesForPeriod(
        int $vehicleId,
        string $startDate,
        string $endDate,
    ): array {
        $contracts = $this->contracts->findAllOverlapping($vehicleId, $startDate, $endDate);

        if ($contracts->isEmpty()) {
            return [];
        }

        $windowStart = CarbonImmutable::parse($startDate);
        $windowEnd = CarbonImmutable::parse($endDate);

        $dates = [];
        foreach ($contracts as $contract) {
            $cursor = $contract->start_date->isAfter($windowStart)
                ? CarbonImmutable::parse($contract->start_date->toDateString())
                : $windowStart;
            $stop = $contract->end_date->isBefore($windowEnd)
                ? CarbonImmutable::parse($contract->end_date->toDateString())
                : $windowEnd;

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
