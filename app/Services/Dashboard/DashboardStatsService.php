<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Contracts\Repositories\User\Company\CompanyReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Data\User\Dashboard\DashboardKpiComparisonData;
use App\Data\User\Dashboard\DashboardKpiData;
use App\Data\User\Dashboard\DashboardKpiRecettesData;
use App\Data\User\Dashboard\DashboardPendingTasksData;
use App\Data\User\Dashboard\DashboardYearHistoryData;
use App\DTO\Fiscal\ContractsByPair;
use App\Exceptions\Fiscal\FiscalCalculationException;
use App\Models\Company;
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
     * 4 KPIs fiscaux « Présent » de l'année calendaire courante +
     * comparaison vs même période Y-1 (chantier perf Dashboard
     * 2026-05-17 · split en `Inertia::defer` distinct du calcul recettes
     * locatives qui ajoutait ~60 queries SQL au chemin critique).
     *
     * La date de référence est aujourd'hui. Pré-charge en bulk les
     * contrats / véhicules / indispos pour les 2 années couvertes
     * (current + Y-1) via {@see DashboardScopeContext} · évite 2× les
     * chargements indépendants.
     */
    public function computeKpisFiscal(int $year): DashboardKpiData
    {
        $today = CarbonImmutable::today();
        $context = $this->buildScopeContext($year - 1, $year);

        $current = $this->computePeriodMetrics($year, $today, $context);

        $previousYearEnd = $today->subYear();
        $previous = $this->computePeriodMetrics($year - 1, $previousYearEnd, $context);

        $comparison = $previous['hasData']
            ? new DashboardKpiComparisonData(
                year: $year - 1,
                endDate: $previousYearEnd->toDateString(),
                joursVehicule: $previous['joursVehicule'],
                contracts: $previous['contracts'],
                taxesDues: $previous['taxesDues'],
                tauxOccupation: $previous['tauxOccupation'],
                deltaJoursVehiculePercent: self::deltaPercent($current['joursVehicule'], $previous['joursVehicule']),
                deltaContractsPercent: self::deltaPercent($current['contracts'], $previous['contracts']),
                deltaTaxesDuesPercent: self::deltaPercent($current['taxesDues'], $previous['taxesDues']),
                deltaTauxOccupationPoints: round($current['tauxOccupation'] - $previous['tauxOccupation'], 1),
            )
            : null;

        return new DashboardKpiData(
            year: $year,
            joursVehicule: $current['joursVehicule'],
            contracts: $current['contracts'],
            contractsActiveNow: $current['contractsActiveNow'],
            taxesDues: $current['taxesDues'],
            tauxOccupation: $current['tauxOccupation'],
            previousYearComparison: $comparison,
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
        $current = $this->computeRecettesLocativesCentsForYear($year);
        $previous = $this->computeRecettesLocativesCentsForYear($year - 1);

        return new DashboardKpiRecettesData(
            year: $year,
            recettesLocativesCents: $current,
            previousYearRecettesLocativesCents: $previous > 0 ? $previous : null,
            deltaRecettesLocativesPercent: self::deltaPercent($current, $previous),
            previousYear: $previous > 0 ? $year - 1 : null,
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
     * Mémoïsation per-couple `(companyId, year)` via
     * {@see memoizedRecettesCents} · les appels répétés (ex.
     * `computeKpisRecettes` qui calcule year + Y-1, ou
     * `computeHistory` sur 8 années) ne paient `byCompanyForYear`
     * qu'une seule fois par couple.
     */
    private function computeRecettesLocativesCentsForYear(int $year): int
    {
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
                // Mémoïsation par couple (companyId, year) via
                // `memoizedRecettesCents` · si l'année est déjà
                // calculée (cas où `computeKpisRecettes` a tourné
                // dans la même requête, théorique car defer séparé),
                // hit cache local · 0 query.
                recettesLocativesCents: $this->computeRecettesLocativesCentsForYear($year),
            );
        }

        return $history;
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
