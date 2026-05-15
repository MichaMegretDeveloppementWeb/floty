<?php

declare(strict_types=1);

namespace App\Services\Vehicle;

use App\Contracts\Repositories\User\Unavailability\UnavailabilityReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Data\Shared\YearScopeData;
use App\Data\User\Unavailability\UnavailabilityData;
use App\Data\User\Vehicle\VehicleData;
use App\Data\User\Vehicle\VehicleYearStatsData;
use App\Exceptions\Fiscal\FiscalCalculationException;
use App\Fiscal\Registry\FiscalRuleRegistry;
use App\Models\Unavailability;
use App\Models\Vehicle;
use App\Services\Billing\RentalPriceCalculator;
use App\Services\Contract\ContractQueryService;
use App\Services\Fiscal\AvailableYearsResolver;
use App\Services\Fiscal\FleetFiscalAggregator;
use Illuminate\Support\Collection;

/**
 * Détail complet d'un véhicule pour la page Show (extrait de
 * `VehicleQueryService` pour respecter SRP · Lot 4 D09 / F-14-003).
 *
 * Concentre le flot le plus complexe du domaine Vehicle qui agrège tout
 * pour la page Show ·
 *   - identité + caractéristiques fiscales actives
 *   - historique antéchronologique des versions VFC
 *   - statistiques d'utilisation (KPI Présent + Évolution)
 *   - usageStats initial (carte Utilisation & Répartition · délégué à
 *     `VehicleAggregatesService::buildUsageStats` pour réutiliser le
 *     même algo que les endpoints lazy)
 *   - busyDates pour le DateRangePicker du modal indispos
 *
 * **Doctrine temporelle (chantier η Phase 2)** · 3 lentilles distinctes ·
 *   - Présent (`kpiYear` + `kpiStats`) · année calendaire courante,
 *     non mutable depuis l'UI
 *   - Évolution (`history[]`) · `[minYear..kpiYear-1]`, lignes neutres
 *     (zéros) comprises pour les années sans contrat
 *   - Exploration (`usageStats` + `selectedYear`) · pilotée par le
 *     sélecteur d'année partagé (Timeline + Breakdown + FullYearTax)
 */
final class VehicleDetailService
{
    public function __construct(
        private readonly VehicleReadRepositoryInterface $vehicles,
        private readonly UnavailabilityReadRepositoryInterface $unavailabilityRepo,
        private readonly ContractQueryService $contracts,
        private readonly FleetFiscalAggregator $aggregator,
        private readonly AvailableYearsResolver $availableYears,
        private readonly FiscalRuleRegistry $fiscalRules,
        private readonly RentalPriceCalculator $rentalPrice,
        // Couplage assumé · `findVehicleData` doit composer le `usageStats`
        // initial avec exactement le même algo que `usageStatsForYear`
        // (endpoint lazy). Réutiliser `VehicleAggregatesService::buildUsageStats`
        // évite la duplication des ~70 l de timeline + breakdown.
        private readonly VehicleAggregatesService $aggregates,
    ) {}

    /**
     * Représentation complète d'un véhicule pour la page Show :
     * identité + caractéristiques fiscales actives + historique
     * antéchronologique des versions VFC + statistiques d'utilisation.
     *
     * Lève `ModelNotFoundException` (rendu 404 par Laravel) si l'id
     * n'existe pas.
     */
    public function findVehicleData(int $id): VehicleData
    {
        $vehicle = $this->vehicles->findByIdWithFiscalHistory($id);

        // Charge la Collection brute UNE fois et la propage à
        // `buildUsageStats` + à la composition des DTO de la timeline
        // - auparavant la même requête `findForVehicle` partait deux
        // fois (via `UnavailabilityQueryService` puis re-direct repo).
        $unavailabilityModels = $this->unavailabilityRepo->findForVehicle($vehicle->id);
        $unavailabilityDtos = $unavailabilityModels
            ->map(static fn (Unavailability $u): UnavailabilityData => UnavailabilityData::fromModel($u))
            ->values()
            ->all();

        // Présent · KPI sur l'année calendaire courante.
        $kpiYear = $this->availableYears->currentYear();
        $kpiStats = $this->computeVehicleYearStats($vehicle, $kpiYear, $unavailabilityModels);
        $kpiFiscalAvailable = in_array(
            $kpiYear,
            $this->fiscalRules->registeredYears(),
            true,
        );

        // Évolution · couvre `[minYear..kpiYear-1]`, lignes neutres
        // pour les années sans contrat (cohérent avec Phase 1 Company).
        $history = [];
        $minYear = $this->availableYears->minYear();
        for ($year = $kpiYear - 1; $year >= $minYear; $year--) {
            $history[] = $this->computeVehicleYearStats($vehicle, $year, $unavailabilityModels);
        }

        // Exploration · `usageStats` initialisé sur `currentYear`. Les
        // autres années sont fetchées à la demande côté front via les
        // endpoints lazy `usageStatsForYear` / `fullYearBreakdownForYear`
        // avec cache client (composable `useYearLazy`). Évite le pré-calcul
        // backend pour des années qui ne seront jamais consultées.
        $initialYear = $kpiYear;

        return VehicleData::fromModel(
            $vehicle,
            $this->aggregates->buildUsageStats($vehicle, $initialYear, $unavailabilityModels),
            $unavailabilityDtos,
            $this->buildBusyDates($vehicle->id, $initialYear),
            kpiYear: $kpiYear,
            kpiStats: $kpiStats,
            kpiFiscalAvailable: $kpiFiscalAvailable,
            history: $history,
            selectedYear: $initialYear,
            yearScope: YearScopeData::fromResolver($this->availableYears),
        );
    }

    /**
     * Calcule les stats annuelles d'un véhicule pour une année donnée
     * (jours utilisés, nombre de contrats, taxe réelle, taxe pleine).
     * Utilisé pour les KPI Présent et chaque ligne de history.
     *
     * Tolère l'absence de configuration fiscale sur l'année : retourne
     * `actualTax = 0` et `fullYearTax = 0` plutôt que de crasher la page
     * · l'utilisateur voit quand même les jours et le compte de contrats.
     *
     * @param  Collection<int, Unavailability>  $unavailabilityModels  Indispos pré-chargées (toutes années).
     */
    private function computeVehicleYearStats(
        Vehicle $vehicle,
        int $year,
        Collection $unavailabilityModels,
    ): VehicleYearStatsData {
        $contractsByPair = $this->contracts->loadContractsByPairForVehicle($vehicle->id, $year);
        $vehicleUnavailabilities = $unavailabilityModels->all();

        $daysUsed = 0;
        $contractsCount = 0;
        foreach ($contractsByPair->pairsForVehicle($vehicle->id) as $pairContracts) {
            foreach ($pairContracts as $contract) {
                $daysUsed += $contract->countDaysInYear($year);
                $contractsCount++;
            }
        }

        $actualTax = 0.0;
        $fullYearTax = 0.0;
        try {
            $breakdown = $this->aggregator->vehicleAnnualTaxBreakdownByCompany(
                $vehicle,
                $contractsByPair,
                $vehicleUnavailabilities,
                $year,
            );
            foreach ($breakdown as $row) {
                $actualTax += (float) $row['taxTotal'];
            }
            $fullYearTax = $this->aggregator->vehicleFullYearTax($vehicle, $year);
        } catch (FiscalCalculationException) {
            // Année hors registry fiscal · chiffres taxes laissés à 0.
        }

        // Phase 13 D5.10.L · prix location single-vehicle · usage Show ·
        // pas besoin du batched ici (1 véhicule = 2 SQL acceptables).
        $rentalCents = $this->rentalPrice->forVehicleAndYear($vehicle->id, $year);
        $rentalPrice = $rentalCents === null ? null : $rentalCents / 100;

        return new VehicleYearStatsData(
            year: $year,
            daysUsed: $daysUsed,
            contractsCount: $contractsCount,
            actualTax: round($actualTax, 2, PHP_ROUND_HALF_UP),
            fullYearTax: round($fullYearTax, 2, PHP_ROUND_HALF_UP),
            rentalPrice: $rentalPrice,
        );
    }

    /**
     * Liste flat des dates ISO (Y-m-d) déjà occupées par un contrat
     * actif sur le véhicule pour l'année. Alimente le `DateRangePicker`
     * du modal indispos pour griser les jours non-sélectionnables.
     *
     * Bornée à `[01-01-Y, 31-12-Y]` - les contrats hors fenêtre ne
     * bloquent pas l'UI (l'Action vérifie de toute façon avant écriture
     * si l'utilisateur ouvre une plage débordante).
     *
     * @return list<string>
     */
    private function buildBusyDates(int $vehicleId, int $year): array
    {
        return $this->contracts->findDatesForVehicleInRange(
            $vehicleId,
            sprintf('%d-01-01', $year),
            sprintf('%d-12-31', $year),
        );
    }
}
