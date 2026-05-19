<?php

declare(strict_types=1);

namespace App\DTO\Fiscal;

use App\Models\Contract;
use App\Services\Fiscal\FleetFiscalAggregator;

/**
 * Active contracts of a fiscal year, grouped by (vehicle, company) pair.
 *
 * Per ADR-0014 the fiscal engine receives the raw contract list for each
 * pair (rather than a pre-aggregated annual cumul) so each rule decides
 * how to treat it — for instance R-2024-021 qualifies each contract
 * individually as LCD or not.
 *
 * Keys of the underlying map have the shape `"{vehicleId}|{companyId}"`.
 */
final readonly class ContractsByPair
{
    /**
     * @param  array<string, list<Contract>>  $byPair  "vehicleId|companyId" → contracts of the pair on the year.
     */
    public function __construct(public array $byPair) {}

    /**
     * Contracts of a specific pair on the year.
     *
     * @return list<Contract>
     */
    public function forPair(int $vehicleId, int $companyId): array
    {
        return $this->byPair[$vehicleId.'|'.$companyId] ?? [];
    }

    /**
     * Map of companyId → contracts for a given vehicle.
     *
     * @return array<int, list<Contract>>
     */
    public function pairsForVehicle(int $vehicleId): array
    {
        $result = [];
        $prefix = $vehicleId.'|';
        foreach ($this->byPair as $key => $contracts) {
            if (str_starts_with($key, $prefix)) {
                $companyId = (int) substr($key, strlen($prefix));
                $result[$companyId] = $contracts;
            }
        }

        return $result;
    }

    /**
     * Map of vehicleId → contracts for a given company; symmetric to
     * {@see pairsForVehicle}. Used by the aggregator to sum a company's
     * annual tax without iterating the full fleet.
     *
     * @return array<int, list<Contract>>
     */
    public function pairsForCompany(int $companyId): array
    {
        $result = [];
        $suffix = '|'.$companyId;
        foreach ($this->byPair as $key => $contracts) {
            if (str_ends_with($key, $suffix)) {
                $vehicleId = (int) substr($key, 0, -strlen($suffix));
                $result[$vehicleId] = $contracts;
            }
        }

        return $result;
    }

    /**
     * Iterate over every (vehicle, company) pair recorded in the DTO.
     *
     * @return iterable<array{vehicleId: int, companyId: int, contracts: list<Contract>}>
     */
    public function vehicleCompanyPairs(): iterable
    {
        foreach ($this->byPair as $key => $contracts) {
            [$vehicleId, $companyId] = explode('|', $key, 2);
            yield [
                'vehicleId' => (int) $vehicleId,
                'companyId' => (int) $companyId,
                'contracts' => $contracts,
            ];
        }
    }

    /**
     * Total contract-days used by a given company across the whole fleet
     * on the year. Raw KPI — no LCD / reductive unavailability deduction;
     * use {@see FleetFiscalAggregator} for the taxable amount.
     */
    public function daysByCompany(int $companyId, int $year): int
    {
        $total = 0;
        foreach ($this->byPair as $key => $contracts) {
            [, $cId] = explode('|', $key, 2);
            if ((int) $cId !== $companyId) {
                continue;
            }
            foreach ($contracts as $contract) {
                $total += $contract->countDaysInYear($year);
            }
        }

        return $total;
    }

    /**
     * Total contract-days across every pair on the year (Dashboard KPI).
     * A single calendar day counts twice when carried by two distinct
     * pairs — consistent with the fiscal total that taxes each pair
     * independently.
     */
    public function totalDays(int $year): int
    {
        $total = 0;
        foreach ($this->byPair as $contracts) {
            foreach ($contracts as $contract) {
                $total += $contract->countDaysInYear($year);
            }
        }

        return $total;
    }
}
