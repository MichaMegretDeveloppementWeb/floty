<?php

declare(strict_types=1);

namespace App\DTO\Fiscal;

use App\Models\Contract;
use App\Services\Fiscal\FleetFiscalAggregator;

/**
 * Liste des contrats actifs sur l'année regroupés par couple
 * (véhicule, entreprise utilisatrice).
 *
 * Remplace `AnnualCumulByPair` (cumul annuel agrégé) - la sémantique de
 * cumul-annuel-par-couple a été révoquée par ADR-0014. Le moteur fiscal
 * reçoit désormais la matière brute (la liste des contrats du couple)
 * et chaque règle fiscale décide (ex. R-2024-021 qualifie chaque
 * contrat individuellement comme LCD ou non).
 *
 * Les clés du map sous-jacent sont au format `"{vehicleId}|{companyId}"`.
 */
final readonly class ContractsByPair
{
    /**
     * @param  array<string, list<Contract>>  $byPair  "vehicleId|companyId" → contrats du couple sur l'année
     */
    public function __construct(public array $byPair) {}

    /**
     * Contrats d'un couple précis sur l'année.
     *
     * @return list<Contract>
     */
    public function forPair(int $vehicleId, int $companyId): array
    {
        return $this->byPair[$vehicleId.'|'.$companyId] ?? [];
    }

    /**
     * Map companyId → contrats pour un véhicule donné.
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
     * Map vehicleId → contrats pour une entreprise donnée - symétrique
     * de {@see pairsForVehicle}. Utilisé par l'aggregator pour sommer
     * la taxe annuelle d'une entreprise sans ré-itérer toute la flotte.
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
     * Itérateur sur tous les couples renseignés (pratique pour
     * sommer toute la flotte sans recharger).
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
     * Total des jours-contrat occupés par une entreprise sur la
     * flotte de l'année (tous véhicules confondus).
     *
     * Note : KPI brut (sans déduction LCD ni indispos réductrices) -
     * pour la valeur fiscalement taxable, passer par
     * {@see FleetFiscalAggregator}.
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
                $total += count($contract->expandToDaysInYear($year));
            }
        }

        return $total;
    }

    /**
     * Total des jours-contrat occupés sur l'année tous couples
     * confondus - KPI Dashboard.
     *
     * Sémantique : un même jour compté deux fois s'il est porté par
     * deux pairs distincts (cohérent avec le total fiscal qui taxe
     * chaque couple indépendamment).
     */
    public function totalDays(int $year): int
    {
        $total = 0;
        foreach ($this->byPair as $contracts) {
            foreach ($contracts as $contract) {
                $total += count($contract->expandToDaysInYear($year));
            }
        }

        return $total;
    }
}
