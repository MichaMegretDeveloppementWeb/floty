<?php

declare(strict_types=1);

namespace App\DTO\Fiscal;

use App\Contracts\Repositories\User\Assignment\AssignmentReadRepositoryInterface;

/**
 * Cumul annuel des jours d'attribution par couple (véhicule, entreprise).
 *
 * Construit en une seule requête SQL agrégée par
 * {@see AssignmentReadRepositoryInterface::loadAnnualCumul()},
 * puis passé aux agrégateurs fiscaux. Les controllers ne le manipulent
 * jamais directement.
 *
 * Les clés du map sous-jacent sont au format `"{vehicleId}|{companyId}"`.
 */
final readonly class AnnualCumulByPair
{
    /**
     * @param  array<string, int>  $byPair  Clés "vehicleId|companyId" → nb de jours
     */
    public function __construct(public array $byPair) {}

    /**
     * Jours cumulés sur l'année pour un couple précis.
     */
    public function forPair(int $vehicleId, int $companyId): int
    {
        return $this->byPair[$vehicleId.'|'.$companyId] ?? 0;
    }

    /**
     * Total annuel des jours utilisés par une entreprise sur la flotte.
     */
    public function daysByCompany(int $companyId): int
    {
        $total = 0;
        foreach ($this->vehicleCompanyPairs() as ['companyId' => $cId, 'days' => $days]) {
            if ($cId === $companyId) {
                $total += $days;
            }
        }

        return $total;
    }

    /**
     * Map companyId → jours pour un véhicule donné.
     *
     * @return array<int, int>
     */
    public function pairsForVehicle(int $vehicleId): array
    {
        $result = [];
        $prefix = $vehicleId.'|';
        foreach ($this->byPair as $key => $days) {
            if (str_starts_with($key, $prefix)) {
                $companyId = (int) substr($key, strlen($prefix));
                $result[$companyId] = $days;
            }
        }

        return $result;
    }

    /**
     * Itérateur sur tous les couples renseignés (pratique pour
     * sommer toute la flotte sans recharger).
     *
     * @return iterable<array{vehicleId: int, companyId: int, days: int}>
     */
    public function vehicleCompanyPairs(): iterable
    {
        foreach ($this->byPair as $key => $days) {
            [$vehicleId, $companyId] = explode('|', $key, 2);
            yield [
                'vehicleId' => (int) $vehicleId,
                'companyId' => (int) $companyId,
                'days' => $days,
            ];
        }
    }
}
