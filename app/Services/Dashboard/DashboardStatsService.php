<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Contracts\Repositories\User\Company\CompanyReadRepositoryInterface;
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
use App\Models\Company;
use App\Models\Vehicle;
use App\Services\Billing\BillingBreakdownService;
use App\Services\Contract\ContractQueryService;
use App\Services\Fiscal\AvailableYearsResolver;
use App\Services\Fiscal\FleetFiscalAggregator;
use App\Services\Shared\Fiscal\FiscalYearContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

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

    /**
     * Nombre maximum de barres dans le graphique « Évolution ». Si le
     * scope dynamique des contrats remonte plus loin (ex. 20 ans), on
     * tronque aux N dernières années pour garder une lecture visuelle
     * claire. Si scope plus court (3 ans), on affiche tout.
     */
    private const HISTORY_MAX_YEARS = 8;

    /**
     * Mémoïsation per-request des recettes locatives par couple
     * (entreprise, année) pour éviter de payer plusieurs fois
     * `BillingBreakdownService::byCompanyForYear` (3 queries SQL chacun)
     * quand {@see computeKpis} et {@see computeHistory} couvrent des
     * années identiques (year courant + Y-1). Clé · `{companyId}|{year}`.
     *
     * @var array<string, int>
     */
    private array $recettesCentsMemo = [];

    /**
     * Mémoïsation per-request de `companies->findAllForOptions()` qui
     * est appelé par chaque construction de scope context. La liste
     * change rarement et un Service Laravel est resolved per-request.
     *
     * @var Collection<int, Company>|null
     */
    private ?Collection $cachedCompanies = null;

    public function __construct(
        private readonly VehicleReadRepositoryInterface $vehicles,
        private readonly ContractQueryService $contracts,
        private readonly FleetFiscalAggregator $aggregator,
        private readonly FiscalYearContext $yearContext,
        private readonly AvailableYearsResolver $availableYears,
        private readonly BillingBreakdownService $billingBreakdown,
        private readonly CompanyReadRepositoryInterface $companies,
        private readonly DashboardPendingTasksAggregator $pendingTasksAggregator,
    ) {}

    /**
     * KPIs « Présent » de l'année calendaire courante + comparaison vs
     * même période Y-1. La date de référence est aujourd'hui.
     *
     * F-21-001/002 · pré-charge en bulk les contrats / véhicules /
     * indispos / recettes pour les 2 années couvertes (current + Y-1)
     * via {@see DashboardScopeContext} · évite 2× les chargements
     * indépendants de loadContractsByPair / findByIdsIndexed /
     * loadUnavailabilitiesByVehicle.
     */
    public function computeKpis(int $year): DashboardKpiData
    {
        $today = CarbonImmutable::today();
        $context = $this->buildScopeContext($year - 1, $year);

        $current = $this->computePeriodMetrics($year, $today, $context);

        $previousYearEnd = $today->subYear();
        $previous = $this->computePeriodMetrics($year - 1, $previousYearEnd, $context);

        // Recettes locatives : full year (jan-déc), indépendant de upToDate.
        // Pour Y courante, somme tous les mois 1..12 (réalisés + prévus).
        // Pour Y-1, l'année est complète, somme directement les 12 mois.
        $recettesCurrent = $this->computeRecettesLocativesCentsForYear($year, $context);
        $recettesPrevious = $this->computeRecettesLocativesCentsForYear($year - 1, $context);

        $comparison = $previous['hasData'] || $recettesPrevious > 0
            ? new DashboardKpiComparisonData(
                year: $year - 1,
                endDate: $previousYearEnd->toDateString(),
                joursVehicule: $previous['joursVehicule'],
                contracts: $previous['contracts'],
                taxesDues: $previous['taxesDues'],
                tauxOccupation: $previous['tauxOccupation'],
                recettesLocativesCents: $recettesPrevious,
                deltaJoursVehiculePercent: self::deltaPercent($current['joursVehicule'], $previous['joursVehicule']),
                deltaContractsPercent: self::deltaPercent($current['contracts'], $previous['contracts']),
                deltaTaxesDuesPercent: self::deltaPercent($current['taxesDues'], $previous['taxesDues']),
                deltaTauxOccupationPoints: round($current['tauxOccupation'] - $previous['tauxOccupation'], 1),
                deltaRecettesLocativesPercent: self::deltaPercent($recettesCurrent, $recettesPrevious),
            )
            : null;

        return new DashboardKpiData(
            year: $year,
            joursVehicule: $current['joursVehicule'],
            contracts: $current['contracts'],
            contractsActiveNow: $current['contractsActiveNow'],
            taxesDues: $current['taxesDues'],
            tauxOccupation: $current['tauxOccupation'],
            recettesLocativesCents: $recettesCurrent,
            previousYearComparison: $comparison,
        );
    }

    /**
     * Recettes locatives HT cumulées pour une année donnée, **plein
     * année** (mois 1..12). Itère toutes les entreprises connues et
     * somme `yearTotalCentsPartial` (mode partiel : véhicules sans
     * tarif annuel exclus, cf. T11 E.17).
     *
     * Sémantique « total réalisé + prévu » pour l'année courante :
     * `BillingBreakdownService::byCompanyForYear` calcule chaque mois
     * via `BillingCalculator` qui prend en compte tous les contrats
     * chevauchant le mois, qu'ils soient passés ou futurs. Aucun
     * filtre temporel sur la date du jour.
     *
     * F-21-001 · si `$context` est fourni et que l'année figure dans
     * la plage pré-calculée, retourne la valeur mémoïsée (zéro query
     * de plus). Sinon comportement standalone.
     */
    private function computeRecettesLocativesCentsForYear(int $year, ?DashboardScopeContext $context = null): int
    {
        if ($context !== null && array_key_exists($year, $context->recettesCentsByYear)) {
            return $context->recettesCentsByYear[$year];
        }

        $total = 0;
        foreach ($this->companiesForOptions() as $company) {
            $total += $this->memoizedRecettesCents((int) $company->id, $year);
        }

        return $total;
    }

    /**
     * Recettes locatives HT (cents) pour un couple (entreprise, année)
     * avec mémoïsation per-request. Évite que `byCompanyForYear`
     * (3 queries SQL chacun) soit appelé deux fois pour un même couple
     * lors d'un même chargement Dashboard (typique · year courant et
     * Y-1 partagés entre {@see computeKpis} et {@see computeHistory}).
     */
    private function memoizedRecettesCents(int $companyId, int $year): int
    {
        $key = $companyId.'|'.$year;
        if (! array_key_exists($key, $this->recettesCentsMemo)) {
            $this->recettesCentsMemo[$key] = $this->billingBreakdown
                ->byCompanyForYear($companyId, $year)
                ->yearTotalCentsPartial;
        }

        return $this->recettesCentsMemo[$key];
    }

    /**
     * Liste des entreprises pour `findAllForOptions()` mémoïsée
     * per-request. Cohérent avec la doctrine "Service Laravel resolved
     * per-request" · pas de pollution cross-request.
     *
     * @return Collection<int, Company>
     */
    private function companiesForOptions(): Collection
    {
        if ($this->cachedCompanies === null) {
            $this->cachedCompanies = $this->companies->findAllForOptions();
        }

        return $this->cachedCompanies;
    }

    /**
     * Construit un contexte mémoïsé pour les calculs Dashboard sur une
     * plage d'années (F-21-001/002). Pré-charge en bulk · les contrats
     * par couple groupés par année, les véhicules concernés, les
     * indispos, et les recettes locatives par année.
     *
     * Coût · ~4 queries SQL fixes (au lieu de ~2N + 5N queries avec
     * une approche year-by-year, où N est le nombre d'années couvertes).
     */
    private function buildScopeContext(int $fromYear, int $toYear): DashboardScopeContext
    {
        // 1 query · tous les contrats actifs croisant le range, groupés
        // par couple (véhicule, entreprise) et dispatchés par année.
        $contractsByYear = $this->contracts->loadContractsByPairForYearRange($fromYear, $toYear);

        // Union de tous les vehicleIds concernés par le scope (un même
        // véhicule peut apparaître dans plusieurs années).
        $allVehicleIds = [];
        foreach ($contractsByYear as $pair) {
            foreach ($pair->vehicleCompanyPairs() as $vc) {
                $allVehicleIds[$vc['vehicleId']] = true;
            }
        }
        $vehicleIdList = array_keys($allVehicleIds);

        // 1 query (ou 0 si scope vide).
        $vehiclesById = $vehicleIdList === []
            ? new Collection
            : $this->vehicles->findByIdsIndexed($vehicleIdList);

        // 1 query (ou 0 si scope vide).
        $unavailabilitiesByVehicleId = $vehicleIdList === []
            ? []
            : $this->contracts->loadUnavailabilitiesByVehicle($vehicleIdList);

        // Recettes locatives · companies mémoïsées per-request +
        // memoization par couple (companyId, year) via
        // {@see memoizedRecettesCents} pour éviter le re-calcul quand
        // computeKpis (year, year-1) et computeHistory (toutes années)
        // partagent les mêmes années.
        $companies = $this->companiesForOptions();
        $recettesCentsByYear = [];
        for ($y = $fromYear; $y <= $toYear; $y++) {
            $total = 0;
            foreach ($companies as $company) {
                $total += $this->memoizedRecettesCents((int) $company->id, $y);
            }
            $recettesCentsByYear[$y] = $total;
        }

        return new DashboardScopeContext(
            contractsByYear: $contractsByYear,
            vehiclesById: $vehiclesById,
            unavailabilitiesByVehicleId: $unavailabilitiesByVehicleId,
            recettesCentsByYear: $recettesCentsByYear,
        );
    }

    /**
     * Historique des 4 KPIs **dans le scope dynamique des contrats**
     * (cf. `AvailableYearsResolver` · doctrine "données métier"). Si
     * le scope remonte au-delà de {@see HISTORY_MAX_YEARS}, on tronque
     * aux N dernières années pour préserver la lisibilité visuelle.
     *
     * Inclut toujours l'année calendaire courante (marquée
     * `isCurrentYear: true`), même si elle n'est pas dans le scope
     * (cas où aucun contrat n'a encore été créé sur l'année en cours).
     *
     * @return list<DashboardYearHistoryData>
     */
    public function computeHistory(): array
    {
        $currentYear = $this->availableYears->currentYear();
        $scope = $this->availableYears->availableYears();
        // Garantit que l'année courante figure dans l'historique
        // (même si scope contrats vide · cas appli neuve).
        if (! in_array($currentYear, $scope, true)) {
            $scope[] = $currentYear;
            sort($scope);
        }
        // Tronque aux N dernières si scope trop large.
        if (count($scope) > self::HISTORY_MAX_YEARS) {
            $scope = array_slice($scope, -self::HISTORY_MAX_YEARS);
        }

        $today = CarbonImmutable::today();
        $context = $this->buildScopeContext(min($scope), max($scope));

        $history = [];
        foreach ($scope as $year) {
            $isCurrent = $year === $currentYear;
            // Année écoulée : on prend la fenêtre complète. Année courante : YTD.
            $endDate = $isCurrent
                ? $today
                : CarbonImmutable::create($year, 12, 31);
            $metrics = $this->computePeriodMetrics($year, $endDate, $context);
            $history[] = new DashboardYearHistoryData(
                year: $year,
                isCurrentYear: $isCurrent,
                joursVehicule: $metrics['joursVehicule'],
                contracts: $metrics['contracts'],
                taxesDues: $metrics['taxesDues'],
                tauxOccupation: $metrics['tauxOccupation'],
                // Recettes locatives plein année (jan-déc), même pour
                // l'année courante (CA prévu inclus). La barre opacifiée
                // « (en cours) » signale la nature prévisionnelle.
                recettesLocativesCents: $this->computeRecettesLocativesCentsForYear($year, $context),
            );
        }

        return $history;
    }

    /**
     * Aperçu opérationnel immédiat · heatmap 30 derniers jours flotte +
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
     * Tâches en attente sur la flotte (Phase 13 D5.15). Délègue à
     * {@see DashboardPendingTasksAggregator} qui agrège les items
     * pending de toutes les entreprises actives via les resolvers
     * existants ({@see PendingDeclarationsResolver},
     * {@see PendingInvoicesResolver}).
     */
    public function computePendingTasks(): DashboardPendingTasksData
    {
        return $this->pendingTasksAggregator->aggregate();
    }

    /**
     * Métriques d'une année à une date de référence (1er janvier →
     * `$upToDate`). Utilisé deux fois par {@see computeKpis} (année
     * courante + même fenêtre Y-1) et N fois par {@see computeHistory}.
     *
     * F-21-001/002 · si `$context` est fourni, lit le pivot contrats
     * + véhicules + indispos depuis le bulk pré-chargé · zéro query
     * de plus. Sinon comportement standalone (fallback).
     *
     * @return array{joursVehicule: int, contracts: int, contractsActiveNow: int, taxesDues: float, tauxOccupation: float, hasData: bool}
     */
    private function computePeriodMetrics(int $year, CarbonImmutable $upToDate, ?DashboardScopeContext $context = null): array
    {
        $contractsByPair = $context !== null
            ? $context->contractsForYear($year)
            : $this->contracts->loadContractsByPair($year);
        $upToDateString = $upToDate->toDateString();
        $todayString = CarbonImmutable::today()->toDateString();

        // Jours-véhicule YTD : on filtre les jours expandus pour ne garder que <= upToDate.
        $joursVehicule = 0;
        // Total contrats : tout contrat ayant chevauché [début année, upToDate]
        // est compté une fois (déduplique cross-pair via id).
        $contractsTotalIds = [];
        // Sous-décompte « actifs aujourd'hui » : start_date <= today <= end_date.
        // Sert uniquement à la lentille Présent (pas Y-1 ni history).
        $contractsActiveNowIds = [];
        foreach ($contractsByPair->vehicleCompanyPairs() as $pair) {
            foreach ($pair['contracts'] as $contract) {
                $days = $contract->expandToDaysInYear($year);
                foreach ($days as $day) {
                    if ($day <= $upToDateString) {
                        $joursVehicule++;
                    }
                }
                // Total : tout contrat dont la plage croise [1er janvier, upToDate]
                // → start_date <= upToDate (la fin est forcément >= 1er janvier
                // si on est ici, vu que loadContractsByPair filtre déjà).
                if ($contract->start_date->toDateString() <= $upToDateString) {
                    $contractsTotalIds[$contract->id] = true;
                }
                // Actif aujourd'hui (∀ year, on regarde la photographie maintenant) :
                if ($contract->start_date->toDateString() <= $todayString
                    && $contract->end_date->toDateString() >= $todayString
                ) {
                    $contractsActiveNowIds[$contract->id] = true;
                }
            }
        }
        $contractsCount = count($contractsTotalIds);
        $contractsActiveNowCount = count($contractsActiveNowIds);

        // Taxes YTD : approximation linéaire de la taxe annuelle.
        // Cf. doctrine de classe ci-dessus.
        $taxesAnnuelles = $this->safeFleetAnnualTax($contractsByPair, $year, $context);
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
            'contracts' => $contractsCount,
            'contractsActiveNow' => $contractsActiveNowCount,
            'taxesDues' => $taxesDues,
            'tauxOccupation' => $tauxOccupation,
            'hasData' => $joursVehicule > 0 || $contractsCount > 0 || $taxesAnnuelles > 0,
        ];
    }

    /**
     * Encapsule l'appel à `fleetAnnualTax` avec tolérance des années
     * sans règles fiscales (cf. doctrine "données métier ⊥ règles
     * fiscales", chantier η Phase 3). Renvoie 0.0 si l'année n'a pas
     * de boot configuré.
     *
     * F-21-001/002 · si `$context` est fourni, lit les véhicules et
     * indispos depuis le bulk pré-chargé · zéro query de plus. Sinon
     * comportement standalone (fallback).
     */
    private function safeFleetAnnualTax(ContractsByPair $contractsByPair, int $year, ?DashboardScopeContext $context = null): float
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

            if ($context !== null) {
                // `fleetAnnualTax` itère sur `vehicleCompanyPairs()` du
                // pivot · il n'utilise que les véhicules effectivement
                // présents, le superset `$context->vehiclesById` est
                // donc safe (les véhicules en trop sont ignorés).
                $vehiclesById = $context->vehiclesById;
                $unavailabilitiesByVehicleId = $context->unavailabilitiesByVehicleId;
            } else {
                $vehiclesById = $this->vehicles->findByIdsIndexed($vehicleIdList);
                $unavailabilitiesByVehicleId = $this->contracts->loadUnavailabilitiesByVehicle($vehicleIdList);
            }

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
                $start = $unavail->start_date->toImmutable();
                $end = $unavail->end_date !== null
                    ? $unavail->end_date->toImmutable()
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
