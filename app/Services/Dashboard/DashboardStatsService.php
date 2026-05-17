<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Data\User\Dashboard\DashboardHistoryPointData;
use App\Data\User\Dashboard\DashboardKpiData;
use App\Data\User\Dashboard\DashboardKpiRecettesData;
use App\Data\User\Dashboard\DashboardPendingTasksData;
use App\DTO\Fiscal\ContractsByPair;
use App\Exceptions\Fiscal\FiscalCalculationException;
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
    /**
     * Nombre maximum de barres dans le graphique « Évolution ». Si le
     * scope dynamique des contrats remonte plus loin, on tronque aux N
     * dernières années pour garder une lecture visuelle claire ET
     * limiter le coût du pipeline fiscal (chantier perf Dashboard
     * 2026-05-17 v3 · réduit de 8 à 4 · 240 → 120 pipeline runs).
     */
    private const HISTORY_MAX_YEARS = 4;

    public function __construct(
        private readonly VehicleReadRepositoryInterface $vehicles,
        private readonly ContractQueryService $contracts,
        private readonly FleetFiscalAggregator $aggregator,
        private readonly FiscalYearContext $yearContext,
        private readonly AvailableYearsResolver $availableYears,
        private readonly BillingBreakdownService $billingBreakdown,
        private readonly DashboardPendingTasksAggregator $pendingTasksAggregator,
    ) {}

    /**
     * 4 KPIs fiscaux « Présent » de l'année calendaire courante.
     *
     * **Comparaison Y-1 retirée** (chantier perf Dashboard 2026-05-17 v3) ·
     * l'historique multi-années (`computeHistory`, désormais chargé à la
     * demande via un bouton) sert de support visuel à la comparaison
     * temporelle. Gain CPU · le pipeline fiscal ne tourne plus que sur 1
     * année au mount Dashboard (au lieu de 2) · ~50% du temps économisé.
     *
     * La date de référence est aujourd'hui. Pré-charge en bulk les
     * contrats / véhicules / indispos pour l'année courante via
     * {@see DashboardScopeContext}.
     */
    public function computeKpisFiscal(int $year): DashboardKpiData
    {
        $today = CarbonImmutable::today();
        $context = $this->buildScopeContext($year, $year);

        $current = $this->computePeriodMetrics($year, $today, $context);

        return new DashboardKpiData(
            year: $year,
            joursVehicule: $current['joursVehicule'],
            contracts: $current['contracts'],
            contractsActiveNow: $current['contractsActiveNow'],
            taxesDues: $current['taxesDues'],
            tauxOccupation: $current['tauxOccupation'],
        );
    }

    /**
     * Carte « Recettes locatives » isolée des 4 KPIs fiscaux (chantier
     * perf Dashboard 2026-05-17). Sert en `Inertia::defer` distinct ·
     * permet à la grille KPIs fiscaux d'apparaître sans attendre les
     * ~60 queries de `BillingBreakdownService::byCompanyForYear`.
     *
     * Sémantique full year (jan-déc), indépendant d'aujourd'hui. Pour
     * l'année courante, somme tous les mois 1..12 (réalisés + prévus).
     * Pour Y-1, l'année est complète.
     */
    public function computeKpisRecettes(int $year): DashboardKpiRecettesData
    {
        // 1 appel batch sur l'année courante uniquement (v3 · drop Y-1).
        // L'historique chart, désormais lazy-load, gère la comparaison
        // temporelle pour les recettes (dimension dédiée).
        $totals = $this->billingBreakdown->totalRecettesForYears([$year]);

        return new DashboardKpiRecettesData(
            year: $year,
            recettesLocativesCents: $totals[$year],
        );
    }

    /**
     * Construit un contexte mémoïsé pour les calculs Dashboard sur une
     * plage d'années (F-21-001/002). Pré-charge en bulk · les contrats
     * par couple groupés par année, les véhicules concernés, les
     * indispos.
     *
     * Coût · ~3 queries SQL fixes (au lieu de ~2N + 3N queries avec
     * une approche year-by-year). Les recettes locatives ne sont plus
     * pré-calculées ici (chargement defer indépendant via
     * {@see computeKpisRecettes()}).
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

        return new DashboardScopeContext(
            contractsByYear: $contractsByYear,
            vehiclesById: $vehiclesById,
            unavailabilitiesByVehicleId: $unavailabilitiesByVehicleId,
        );
    }

    /**
     * Scope d'années pour le graphique Évolution · années où l'entreprise
     * a au moins un contrat actif, tronqué aux N plus récentes
     * (cf. {@see HISTORY_MAX_YEARS}). L'année calendaire courante est
     * toujours incluse, même si scope contrats vide.
     *
     * Doctrine "données métier" · cf. `AvailableYearsResolver`.
     *
     * @return list<int>
     */
    private function historyYearScope(): array
    {
        $currentYear = $this->availableYears->currentYear();
        $scope = $this->availableYears->availableYears();
        if (! in_array($currentYear, $scope, true)) {
            $scope[] = $currentYear;
            sort($scope);
        }
        if (count($scope) > self::HISTORY_MAX_YEARS) {
            $scope = array_slice($scope, -self::HISTORY_MAX_YEARS);
        }

        return $scope;
    }

    /**
     * Historique « Jours-véhicule » · 1 contracts query × scope, somme
     * arithmétique pure (`countDaysInYearUpTo`). Pas de pipeline fiscal,
     * pas de vehicles, pas d'indispos · **dimension la moins chère** ·
     * sert d'onglet par défaut au mount Dashboard (chantier perf v4 ·
     * lazy par onglet).
     *
     * @return list<DashboardHistoryPointData>
     */
    public function computeHistoryJoursVehicule(): array
    {
        $currentYear = $this->availableYears->currentYear();
        $scope = $this->historyYearScope();
        $today = CarbonImmutable::today();
        $contractsByYear = $this->contracts->loadContractsByPairForYearRange(min($scope), max($scope));

        $points = [];
        foreach ($scope as $year) {
            $isCurrent = $year === $currentYear;
            $endDateStr = $isCurrent
                ? $today->toDateString()
                : sprintf('%04d-12-31', $year);

            $jours = 0;
            $contracts = $contractsByYear[$year] ?? new ContractsByPair([]);
            foreach ($contracts->vehicleCompanyPairs() as $pair) {
                foreach ($pair['contracts'] as $contract) {
                    $jours += $contract->countDaysInYearUpTo($year, $endDateStr);
                }
            }

            $points[] = new DashboardHistoryPointData(
                year: $year,
                isCurrentYear: $isCurrent,
                value: $jours,
            );
        }

        return $points;
    }

    /**
     * Historique « Locations » · compte des contrats ayant une activité
     * sur la fenêtre `[1er jan, upToDate]`. Même contracts query que
     * {@see computeHistoryJoursVehicule}, pas de pipeline.
     *
     * @return list<DashboardHistoryPointData>
     */
    public function computeHistoryContracts(): array
    {
        $currentYear = $this->availableYears->currentYear();
        $scope = $this->historyYearScope();
        $today = CarbonImmutable::today();
        $contractsByYear = $this->contracts->loadContractsByPairForYearRange(min($scope), max($scope));

        $points = [];
        foreach ($scope as $year) {
            $isCurrent = $year === $currentYear;
            $endDateStr = $isCurrent
                ? $today->toDateString()
                : sprintf('%04d-12-31', $year);

            $contractIds = [];
            $contracts = $contractsByYear[$year] ?? new ContractsByPair([]);
            foreach ($contracts->vehicleCompanyPairs() as $pair) {
                foreach ($pair['contracts'] as $contract) {
                    if ($contract->start_date->toDateString() <= $endDateStr) {
                        $contractIds[$contract->id] = true;
                    }
                }
            }

            $points[] = new DashboardHistoryPointData(
                year: $year,
                isCurrentYear: $isCurrent,
                value: count($contractIds),
            );
        }

        return $points;
    }

    /**
     * Historique « Taxes dues » · YTD pour année courante, full year
     * pour les passées. **Dimension chère** · exécute le pipeline fiscal
     * × N pairs × scope. Servie en `Inertia::optional` (chargement au
     * clic d'onglet).
     *
     * @return list<DashboardHistoryPointData>
     */
    public function computeHistoryTaxes(): array
    {
        $currentYear = $this->availableYears->currentYear();
        $scope = $this->historyYearScope();
        $today = CarbonImmutable::today();
        $context = $this->buildScopeContext(min($scope), max($scope));

        $points = [];
        foreach ($scope as $year) {
            $isCurrent = $year === $currentYear;
            $endDate = $isCurrent ? $today : CarbonImmutable::create($year, 12, 31);

            $taxesAnnuelles = $this->safeFleetAnnualTax($context->contractsForYear($year), $year, $context);
            $daysInYear = $this->yearContext->daysInYear($year);
            $daysElapsed = $endDate->dayOfYear;
            $taxesDues = $daysInYear > 0 ? round($taxesAnnuelles * $daysElapsed / $daysInYear, 2) : 0.0;

            $points[] = new DashboardHistoryPointData(
                year: $year,
                isCurrentYear: $isCurrent,
                value: $taxesDues,
            );
        }

        return $points;
    }

    /**
     * Historique « Recettes locatives » · plein année (jan-déc) cross-cies.
     * Batch SQL via {@see BillingBreakdownService::totalRecettesForYears}
     * (3-4 queries fixes quel que soit le scope). Servie en
     * `Inertia::optional`.
     *
     * @return list<DashboardHistoryPointData>
     */
    public function computeHistoryRecettes(): array
    {
        $currentYear = $this->availableYears->currentYear();
        $scope = $this->historyYearScope();

        $recettesByYear = $this->billingBreakdown->totalRecettesForYears($scope);

        $points = [];
        foreach ($scope as $year) {
            $points[] = new DashboardHistoryPointData(
                year: $year,
                isCurrentYear: $year === $currentYear,
                value: $recettesByYear[$year],
            );
        }

        return $points;
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
     * @return array{joursVehicule: int, contracts: int, contractsActiveNow: int, taxesDues: float, tauxOccupation: float}
     */
    private function computePeriodMetrics(int $year, CarbonImmutable $upToDate, ?DashboardScopeContext $context = null): array
    {
        $contractsByPair = $context !== null
            ? $context->contractsForYear($year)
            : $this->contracts->loadContractsByPair($year);
        $upToDateString = $upToDate->toDateString();
        $todayString = CarbonImmutable::today()->toDateString();

        // Jours-véhicule YTD · arithmétique pure via `countDaysInYearUpTo`
        // (chantier perf Dashboard 2026-05-17). Remplace l'allocation
        // de jusqu'à 365 strings + filtre `<= upToDate` par contrat ·
        // gros consommateur CPU sur history (8 ans × N pairs).
        // Équivalence stricte garantie par
        // `ContractCountVsExpandEquivalenceTest::count_up_to_egal_count_filtre_de_expand`.
        $joursVehicule = 0;
        // Total contrats : tout contrat ayant chevauché [début année, upToDate]
        // est compté une fois (déduplique cross-pair via id).
        $contractsTotalIds = [];
        // Sous-décompte « actifs aujourd'hui » : start_date <= today <= end_date.
        // Sert uniquement à la lentille Présent (pas Y-1 ni history).
        $contractsActiveNowIds = [];
        foreach ($contractsByPair->vehicleCompanyPairs() as $pair) {
            foreach ($pair['contracts'] as $contract) {
                $joursVehicule += $contract->countDaysInYearUpTo($year, $upToDateString);
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
     *
     * **Prewarm VFC** (chantier perf Dashboard 2026-05-17) · pré-charge
     * en 1 query SQL les segments VFC pour tous les véhicules avant
     * d'invoquer le pipeline · sans cela, `fleetAnnualTax` exécute
     * `executeWithSegments` qui fait 1 query VFC par véhicule
     * (N+1 monstrueux · ~70 queries pour 30 véhicules × 2 ans). Le
     * pipeline consomme automatiquement le cache via la branche
     * `executeWithPreloadedVfcSegments`. Équivalence stricte garantie
     * par les tests `prewarm_vfc_segments_equivalent_*` (doctrine
     * `optimisations-conditionnelles.md` stratégie 2). Idempotent ·
     * si déjà prewarmé, no-op.
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

            // Prewarm VFC segments · supprime le N+1 du pipeline.
            // On filtre `$vehiclesById` aux IDs effectivement présents
            // dans le pivot pour ne pas prewarmer inutilement (le
            // superset context peut contenir des véhicules d'autres
            // années).
            $relevantVehicles = $vehiclesById->only($vehicleIdList);
            $this->aggregator->prewarmVfcSegmentsForVehicles($relevantVehicles, $year);

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
}
