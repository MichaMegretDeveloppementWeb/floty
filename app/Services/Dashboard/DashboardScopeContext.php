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
 * (1 query unique), les indispos par véhicule (1 query unique).
 *
 * Les recettes locatives ne sont plus pré-calculées ici (chantier perf
 * Dashboard 2026-05-17 · split en `Inertia::defer` distinct via
 * {@see DashboardStatsService::computeKpisRecettes()}). La mémoïsation
 * par couple `(companyId, year)` reste assurée par
 * `DashboardStatsService::memoizedRecettesCents` en intra-requête.
 */
final readonly class DashboardScopeContext
{
    /**
     * @param  array<int, ContractsByPair>  $contractsByYear  Pivot contrats par année du scope
     * @param  Collection<int, Vehicle>  $vehiclesById  Véhicules indexés (superset des années du scope)
     * @param  array<int, list<Unavailability>>  $unavailabilitiesByVehicleId  Indispos par véhicule (superset)
     */
    public function __construct(
        public array $contractsByYear,
        public Collection $vehiclesById,
        public array $unavailabilitiesByVehicleId,
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
