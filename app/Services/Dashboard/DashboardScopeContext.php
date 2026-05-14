<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\DTO\Fiscal\ContractsByPair;
use App\Models\Unavailability;
use App\Models\Vehicle;
use Illuminate\Support\Collection;

/**
 * Cache mémoire intra-requête pour un calcul Dashboard sur une plage
 * d'années (F-21-001/002 · optimisation perf).
 *
 * Pré-charge en bulk · les contrats groupés par couple (véhicule,
 * entreprise) pour chaque année du scope, les véhicules concernés
 * (1 query unique), les indispos par véhicule (1 query unique), et
 * les recettes locatives par année (calculées une fois par année).
 *
 * Évite que {@see DashboardStatsService::computePeriodMetrics} et
 * {@see DashboardStatsService::computeRecettesLocativesCentsForYear}
 * fassent un rechargement complet pour chaque année du scope (avant
 * fix · 33 queries contrats + 25 invoice_lines + 12 vehicles · après
 * fix · ~3 queries pour les pré-chargements).
 */
final readonly class DashboardScopeContext
{
    /**
     * @param  array<int, ContractsByPair>  $contractsByYear  Pivot contrats par année du scope
     * @param  Collection<int, Vehicle>  $vehiclesById  Véhicules indexés (superset des années du scope)
     * @param  array<int, list<Unavailability>>  $unavailabilitiesByVehicleId  Indispos par véhicule (superset)
     * @param  array<int, int>  $recettesCentsByYear  Recettes HT cumulées (cents) par année
     */
    public function __construct(
        public array $contractsByYear,
        public Collection $vehiclesById,
        public array $unavailabilitiesByVehicleId,
        public array $recettesCentsByYear,
    ) {}

    /**
     * Pivot pour une année du scope · retourne un pivot vide si l'année
     * n'est pas dans le scope (cohérent avec le comportement standalone
     * de `loadContractsByPair` qui ne crash pas sur année sans contrat).
     */
    public function contractsForYear(int $year): ContractsByPair
    {
        return $this->contractsByYear[$year] ?? new ContractsByPair([]);
    }
}
