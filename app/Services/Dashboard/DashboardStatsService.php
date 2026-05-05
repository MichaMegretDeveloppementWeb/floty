<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Data\User\Dashboard\DashboardActivityData;
use App\Data\User\Dashboard\DashboardHeatmapDayData;
use App\Data\User\Dashboard\DashboardKpiComparisonData;
use App\Data\User\Dashboard\DashboardKpiData;
use App\Data\User\Dashboard\DashboardPendingTasksData;
use App\Data\User\Dashboard\DashboardTopVehicleData;
use App\Data\User\Dashboard\DashboardVehicleHeatmapData;
use App\Data\User\Dashboard\DashboardYearHistoryData;
use App\DTO\Fiscal\ContractsByPair;
use App\Exceptions\Fiscal\FiscalCalculationException;
use App\Models\Vehicle;
use App\Services\Contract\ContractQueryService;
use App\Services\Fiscal\AvailableYearsResolver;
use App\Services\Fiscal\FleetFiscalAggregator;
use App\Services\Shared\Fiscal\FiscalYearContext;
use Carbon\CarbonImmutable;

/**
 * Calcule les blocs de données du Dashboard refondu (chantier η Phase 4)
 * selon la doctrine 3 lentilles Présent / Évolution / Exploration
 * alignée sur 4 KPIs pivots : jours-véhicule, contrats actifs, taxes
 * dues, taux d'occupation flotte.
 *
 * **Sémantique du « YTD »** : du 1er janvier au jour calendaire courant.
 * Pour la comparaison vs Y-1, on calcule la même fenêtre du 1er janvier
 * Y-1 au jour-mois équivalent Y-1 (= « même jour-mois un an plus tôt »)
 * pour rester comparable.
 *
 * **Approximation des taxes YTD** : la taxe fiscale est un calcul
 * annuel (pas un cumul jour par jour). On approxime YTD par
 * `fleetAnnualTax × (joursÉcoulés / joursDansAnnée)`. C'est imparfait
 * (les barèmes ne sont pas linéaires) mais suffisant pour donner une
 * tendance cohérente entre Y et Y-1 sur le Dashboard. Pour le détail
 * exact, l'utilisateur consulte la fiche fiscale d'une entreprise ou
 * d'un véhicule.
 */
final class DashboardStatsService
{
    /** Nombre de jours de la heatmap « activité immédiate » de la lentille Exploration. */
    private const HEATMAP_DAYS = 30;

    /** Nombre de véhicules dans le « Top véhicules par taxe YTD ». */
    private const TOP_VEHICLES_COUNT = 3;

    /** Profondeur de l'historique « Évolution » (années passées + année en cours). */
    private const HISTORY_YEARS_BACK = 4;

    public function __construct(
        private readonly VehicleReadRepositoryInterface $vehicles,
        private readonly ContractQueryService $contracts,
        private readonly FleetFiscalAggregator $aggregator,
        private readonly FiscalYearContext $yearContext,
        private readonly AvailableYearsResolver $availableYears,
    ) {}

    /**
     * KPIs « Présent » de l'année calendaire courante + comparaison vs
     * même période Y-1. La date de référence est aujourd'hui.
     */
    public function computeKpis(int $year): DashboardKpiData
    {
        $today = CarbonImmutable::today();
        $current = $this->computePeriodMetrics($year, $today);

        $previousYearEnd = $today->subYear();
        $previous = $this->computePeriodMetrics($year - 1, $previousYearEnd);

        $comparison = $previous['hasData']
            ? new DashboardKpiComparisonData(
                year: $year - 1,
                endDate: $previousYearEnd->toDateString(),
                joursVehicule: $previous['joursVehicule'],
                contractsActifs: $previous['contractsActifs'],
                taxesDues: $previous['taxesDues'],
                tauxOccupation: $previous['tauxOccupation'],
                deltaJoursVehiculePercent: self::deltaPercent($current['joursVehicule'], $previous['joursVehicule']),
                deltaContractsActifsPercent: self::deltaPercent($current['contractsActifs'], $previous['contractsActifs']),
                deltaTaxesDuesPercent: self::deltaPercent($current['taxesDues'], $previous['taxesDues']),
                deltaTauxOccupationPoints: round($current['tauxOccupation'] - $previous['tauxOccupation'], 1),
            )
            : null;

        return new DashboardKpiData(
            year: $year,
            joursVehicule: $current['joursVehicule'],
            contractsActifs: $current['contractsActifs'],
            taxesDues: $current['taxesDues'],
            tauxOccupation: $current['tauxOccupation'],
            previousYearComparison: $comparison,
        );
    }

    /**
     * Historique des 4 KPIs sur les N dernières années (incluant
     * l'année en cours marquée `isCurrentYear: true`).
     *
     * @return list<DashboardYearHistoryData>
     */
    public function computeHistory(): array
    {
        $currentYear = $this->availableYears->currentYear();
        $startYear = $currentYear - self::HISTORY_YEARS_BACK;
        $today = CarbonImmutable::today();

        $history = [];
        for ($year = $startYear; $year <= $currentYear; $year++) {
            $isCurrent = $year === $currentYear;
            // Année écoulée : on prend la fenêtre complète. Année courante : YTD.
            $endDate = $isCurrent
                ? $today
                : CarbonImmutable::create($year, 12, 31);
            $metrics = $this->computePeriodMetrics($year, $endDate);
            $history[] = new DashboardYearHistoryData(
                year: $year,
                isCurrentYear: $isCurrent,
                joursVehicule: $metrics['joursVehicule'],
                contractsActifs: $metrics['contractsActifs'],
                taxesDues: $metrics['taxesDues'],
                tauxOccupation: $metrics['tauxOccupation'],
            );
        }

        return $history;
    }

    /**
     * Aperçu opérationnel immédiat — heatmap 30 derniers jours flotte +
     * top 3 véhicules par taxe YTD.
     */
    public function computeActivity(): DashboardActivityData
    {
        $today = CarbonImmutable::today();
        $year = $this->availableYears->currentYear();

        return new DashboardActivityData(
            last30DaysHeatmap: $this->buildLast30DaysHeatmap($today),
            topExpensiveVehicles: $this->buildTopExpensiveVehicles($year),
        );
    }

    /**
     * Compteurs des tâches en attente — placeholders MVP à `0`. Voir
     * {@see DashboardPendingTasksData} pour la roadmap d'alimentation.
     */
    public function computePendingTasks(): DashboardPendingTasksData
    {
        return new DashboardPendingTasksData(
            pendingDeclarations: 0,
            pendingInvoices: 0,
        );
    }

    /**
     * Métriques d'une année à une date de référence (1er janvier →
     * `$upToDate`). Utilisé deux fois par {@see computeKpis} (année
     * courante + même fenêtre Y-1) et N fois par {@see computeHistory}.
     *
     * @return array{joursVehicule: int, contractsActifs: int, taxesDues: float, tauxOccupation: float, hasData: bool}
     */
    private function computePeriodMetrics(int $year, CarbonImmutable $upToDate): array
    {
        $contractsByPair = $this->contracts->loadContractsByPair($year);
        $upToDateString = $upToDate->toDateString();

        // Jours-véhicule YTD : on filtre les jours expandus pour ne garder que <= upToDate.
        $joursVehicule = 0;
        $contractsActifsCount = 0;
        $contractIdsCounted = [];
        foreach ($contractsByPair->vehicleCompanyPairs() as $pair) {
            foreach ($pair['contracts'] as $contract) {
                $days = $contract->expandToDaysInYear($year);
                foreach ($days as $day) {
                    if ($day <= $upToDateString) {
                        $joursVehicule++;
                    }
                }
                // Contrats actifs au upToDate : start_date <= upToDate <= end_date
                if (! isset($contractIdsCounted[$contract->id])
                    && $contract->start_date->toDateString() <= $upToDateString
                    && $contract->end_date->toDateString() >= $upToDateString
                ) {
                    $contractIdsCounted[$contract->id] = true;
                    $contractsActifsCount++;
                }
            }
        }

        // Taxes YTD : approximation linéaire de la taxe annuelle.
        // Cf. doctrine de classe ci-dessus.
        $taxesAnnuelles = $this->safeFleetAnnualTax($contractsByPair, $year);
        $daysInYear = $this->yearContext->daysInYear($year);
        $daysElapsed = $upToDate->dayOfYear;
        $taxesDues = $daysInYear > 0 ? round($taxesAnnuelles * $daysElapsed / $daysInYear, 2) : 0.0;

        // Taux d'occupation YTD : jours-véhicule réalisés / théoriques.
        // Théoriques = nb véhicules actifs aujourd'hui × jours écoulés.
        // Approximation : on prend l'effectif actuel et non l'effectif moyen.
        $vehiclesActifs = $this->vehicles->countActive();
        $theoriques = $vehiclesActifs * $daysElapsed;
        $tauxOccupation = $theoriques > 0
            ? round(($joursVehicule / $theoriques) * 100, 1)
            : 0.0;

        return [
            'joursVehicule' => $joursVehicule,
            'contractsActifs' => $contractsActifsCount,
            'taxesDues' => $taxesDues,
            'tauxOccupation' => $tauxOccupation,
            'hasData' => $joursVehicule > 0 || $contractsActifsCount > 0 || $taxesAnnuelles > 0,
        ];
    }

    /**
     * Encapsule l'appel à `fleetAnnualTax` avec tolérance des années
     * sans règles fiscales (cf. doctrine "données métier ⊥ règles
     * fiscales", chantier η Phase 3). Renvoie 0.0 si l'année n'a pas
     * de boot configuré.
     */
    private function safeFleetAnnualTax(ContractsByPair $contractsByPair, int $year): float
    {
        try {
            $vehicleIds = [];
            foreach ($contractsByPair->vehicleCompanyPairs() as $pair) {
                $vehicleIds[$pair['vehicleId']] = true;
            }
            $vehicleIdList = array_keys($vehicleIds);

            if ($vehicleIdList === []) {
                return 0.0;
            }

            $vehiclesById = $this->vehicles->findByIdsIndexed($vehicleIdList);
            $unavailabilitiesByVehicleId = $this->contracts->loadUnavailabilitiesByVehicle($vehicleIdList);

            return $this->aggregator->fleetAnnualTax(
                $vehiclesById,
                $contractsByPair,
                $unavailabilitiesByVehicleId,
                $year,
            );
        } catch (FiscalCalculationException) {
            return 0.0;
        }
    }

    /**
     * Construit la heatmap 30 jours (J-29 → J) pour tous les véhicules
     * actifs ou retirés après J-29. Pour chaque jour : statut
     * `occupied` / `unavailable` / `free`.
     *
     * @return list<DashboardVehicleHeatmapData>
     */
    private function buildLast30DaysHeatmap(CarbonImmutable $today): array
    {
        $startWindow = $today->subDays(self::HEATMAP_DAYS - 1);
        $endWindow = $today;

        // On charge les contrats sur les années couvertes (max 2 années
        // car la fenêtre de 30 jours peut chevaucher 2 années).
        $yearsInWindow = array_unique([
            (int) $startWindow->year,
            (int) $endWindow->year,
        ]);

        // Map vehicleId → list<['date' => string, 'status' => string]>
        // Pré-rempli avec 'free' pour chaque jour de la fenêtre.
        $vehicles = $this->loadVehiclesActiveInWindow($startWindow);

        $heatmap = [];
        foreach ($vehicles as $vehicle) {
            $heatmap[$vehicle->id] = [
                'vehicle' => $vehicle,
                'days' => $this->buildEmptyDayWindow($startWindow, self::HEATMAP_DAYS),
            ];
        }

        // Marquer les jours occupés (contrats)
        foreach ($yearsInWindow as $year) {
            $contractsByPair = $this->contracts->loadContractsByPair($year);
            foreach ($contractsByPair->vehicleCompanyPairs() as $pair) {
                if (! isset($heatmap[$pair['vehicleId']])) {
                    continue;
                }
                foreach ($pair['contracts'] as $contract) {
                    $days = $contract->expandToDaysInYear($year);
                    foreach ($days as $day) {
                        if ($day >= $startWindow->toDateString() && $day <= $endWindow->toDateString()) {
                            // Index direct dans le tableau days
                            $idx = $this->dayIndex($day, $startWindow);
                            if ($idx !== null) {
                                $heatmap[$pair['vehicleId']]['days'][$idx]->status = 'occupied';
                            }
                        }
                    }
                }
            }
        }

        // Marquer les indispos (priorité visuelle moindre que occupied)
        $vehicleIds = array_keys($heatmap);
        $unavailabilities = $this->contracts->loadUnavailabilitiesByVehicle($vehicleIds);
        foreach ($unavailabilities as $vehicleId => $items) {
            if (! isset($heatmap[$vehicleId])) {
                continue;
            }
            foreach ($items as $unavail) {
                $start = CarbonImmutable::parse($unavail->start_date->toDateString());
                $end = $unavail->end_date !== null
                    ? CarbonImmutable::parse($unavail->end_date->toDateString())
                    : $endWindow;

                $cursor = $start->isAfter($startWindow) ? $start : $startWindow;
                $stop = $end->isBefore($endWindow) ? $end : $endWindow;

                while (! $cursor->isAfter($stop)) {
                    $idx = $this->dayIndex($cursor->toDateString(), $startWindow);
                    if ($idx !== null && $heatmap[$vehicleId]['days'][$idx]->status === 'free') {
                        $heatmap[$vehicleId]['days'][$idx]->status = 'unavailable';
                    }
                    $cursor = $cursor->addDay();
                }
            }
        }

        // Construire les DTOs finaux
        $result = [];
        foreach ($heatmap as $row) {
            /** @var Vehicle $v */
            $v = $row['vehicle'];
            $result[] = new DashboardVehicleHeatmapData(
                vehicleId: $v->id,
                licensePlate: $v->license_plate,
                brand: $v->brand,
                model: $v->model,
                days: $row['days'],
            );
        }

        return $result;
    }

    /**
     * @return list<Vehicle>
     */
    private function loadVehiclesActiveInWindow(CarbonImmutable $startWindow): array
    {
        return Vehicle::query()
            ->where(function ($q) use ($startWindow): void {
                $q->whereNull('exit_date')
                    ->orWhere('exit_date', '>=', $startWindow->toDateString());
            })
            ->orderBy('license_plate')
            ->get()
            ->all();
    }

    /**
     * @return list<DashboardHeatmapDayData>
     */
    private function buildEmptyDayWindow(CarbonImmutable $startWindow, int $days): array
    {
        $window = [];
        $cursor = $startWindow;
        for ($i = 0; $i < $days; $i++) {
            $window[] = new DashboardHeatmapDayData(
                date: $cursor->toDateString(),
                status: 'free',
            );
            $cursor = $cursor->addDay();
        }

        return $window;
    }

    private function dayIndex(string $day, CarbonImmutable $startWindow): ?int
    {
        $diff = CarbonImmutable::parse($day)->diffInDays($startWindow, true);

        return $diff < 0 || $diff >= self::HEATMAP_DAYS ? null : (int) $diff;
    }

    /**
     * Top N véhicules par taxe YTD (approximation linéaire identique à
     * `computePeriodMetrics`).
     *
     * @return list<DashboardTopVehicleData>
     */
    private function buildTopExpensiveVehicles(int $year): array
    {
        $contractsByPair = $this->contracts->loadContractsByPair($year);

        $vehicleIds = [];
        foreach ($contractsByPair->vehicleCompanyPairs() as $pair) {
            $vehicleIds[$pair['vehicleId']] = true;
        }
        $vehicleIdList = array_keys($vehicleIds);

        if ($vehicleIdList === []) {
            return [];
        }

        $vehiclesById = $this->vehicles->findByIdsIndexed($vehicleIdList);
        $unavailabilitiesByVehicleId = $this->contracts->loadUnavailabilitiesByVehicle($vehicleIdList);

        $today = CarbonImmutable::today();
        $daysInYear = $this->yearContext->daysInYear($year);
        $daysElapsed = $today->dayOfYear;
        $proratio = $daysInYear > 0 ? $daysElapsed / $daysInYear : 1.0;

        $taxByVehicle = [];
        foreach ($vehiclesById as $vehicleId => $vehicle) {
            try {
                $annualTax = $this->aggregator->vehicleAnnualTax(
                    $vehicle,
                    $contractsByPair,
                    $unavailabilitiesByVehicleId[$vehicleId] ?? [],
                    $year,
                );
            } catch (FiscalCalculationException) {
                $annualTax = 0.0;
            }
            $taxByVehicle[$vehicleId] = round($annualTax * $proratio, 2);
        }

        // Tri DESC par taxe YTD
        arsort($taxByVehicle);

        $top = array_slice($taxByVehicle, 0, self::TOP_VEHICLES_COUNT, preserve_keys: true);

        $result = [];
        foreach ($top as $vehicleId => $taxYTD) {
            /** @var Vehicle $v */
            $v = $vehiclesById[$vehicleId];
            $result[] = new DashboardTopVehicleData(
                vehicleId: $v->id,
                licensePlate: $v->license_plate,
                brand: $v->brand,
                model: $v->model,
                taxYearToDate: $taxYTD,
            );
        }

        return $result;
    }

    /**
     * Variation relative en pourcentage. `null` si la base précédente
     * vaut 0 (la division n'a pas de sens, l'UI affiche « n/a » ou
     * juste la valeur courante sans Δ).
     */
    private static function deltaPercent(int|float $current, int|float $previous): ?float
    {
        if ($previous === 0 || $previous === 0.0) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
