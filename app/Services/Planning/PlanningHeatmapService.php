<?php

declare(strict_types=1);

namespace App\Services\Planning;

use App\Contracts\Repositories\User\Company\CompanyReadRepositoryInterface;
use App\Contracts\Repositories\User\Contract\ContractReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleYearlyPricingReadRepositoryInterface;
use App\Data\User\Company\CompanyOptionData;
use App\Data\User\Planning\PlanningHeatmapCompanyVehicleData;
use App\Data\User\Planning\PlanningHeatmapVehicleData;
use App\Data\User\Planning\PlanningHeatmapVehicleFullYearCostsData;
use App\Data\User\Planning\PlanningHeatmapVehicleRealCostsData;
use App\DTO\Fiscal\ContractsByPair;
use App\Exceptions\Billing\MissingPricingException;
use App\Exceptions\Fiscal\FiscalCalculationException;
use App\Models\Company;
use App\Models\Unavailability;
use App\Services\Billing\BillingCalculator;
use App\Services\Billing\Discount\DiscountApplier;
use App\Services\Billing\Discount\DiscountResolver;
use App\Services\Billing\Discount\ResolvedDiscountIndex;
use App\Services\Contract\ContractQueryService;
use App\Services\Fiscal\FleetFiscalAggregator;
use App\Support\Date\IsoWeeks;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\DataCollection;

/**
 * Construction de la matrice véhicules × 52 semaines pour la page
 * « Vue d'ensemble » (planning).
 *
 * **Refonte 04.F (ADR-0014)** : la heatmap consomme désormais les
 * contrats (`ContractQueryService`). Les indispos par véhicule sont
 * passées au moteur fiscal pour permettre à R-2024-008 d'agir sur la
 * matière brute.
 *
 * **Chantier perf 2026-05-16 · slim + defer costs** · {@see buildHeatmap}
 * et {@see buildHeatmapForCompany} ne calculent plus les coûts fiscaux
 * (~630 ms cold sur 64 véhicules). Les 3 montants par véhicule
 * (`annualTaxDue`, `fullYearTax`, `dailyTaxRate`) sont désormais servis
 * via {@see costsForVehicles} en `Inertia::defer` côté controller.
 */
final class PlanningHeatmapService
{
    public function __construct(
        private readonly VehicleReadRepositoryInterface $vehicles,
        private readonly CompanyReadRepositoryInterface $companies,
        private readonly ContractQueryService $contracts,
        private readonly FleetFiscalAggregator $aggregator,
        private readonly VehicleYearlyPricingReadRepositoryInterface $pricings,
        private readonly BillingCalculator $billingCalculator,
        private readonly DiscountResolver $discountResolver,
        private readonly DiscountApplier $discountApplier,
        private readonly ContractReadRepositoryInterface $contractReader,
    ) {}

    /**
     * @return array{vehicles: DataCollection<int, PlanningHeatmapVehicleData>, companies: DataCollection<int, CompanyOptionData>}
     */
    public function buildHeatmap(int $year): array
    {
        $weekDensity = $this->contracts->loadWeekDensity($year);

        $vehicles = $this->vehicles->findAllForHeatmap($year);
        $vehicleIds = $vehicles->pluck('id')->all();
        $unavailabilitiesByVehicleId = $this->contracts->loadUnavailabilitiesByVehicle($vehicleIds);
        $pricingsByVehicleId = $this->pricings->findForVehiclesAndYear($vehicleIds, $year);

        $weeksCount = IsoWeeks::CELLS_PER_YEAR;

        $vehicleRows = [];
        foreach ($vehicles as $vehicle) {
            $fiscal = $vehicle->fiscalCharacteristics->first();
            if ($fiscal === null) {
                continue;
            }

            $weeks = [];
            for ($w = 1; $w <= $weeksCount; $w++) {
                $weeks[] = $weekDensity[$vehicle->id.'|'.$w] ?? 0;
            }

            $vehicleUnavailabilities = $unavailabilitiesByVehicleId[$vehicle->id] ?? [];
            $pricing = $pricingsByVehicleId[$vehicle->id] ?? null;

            $vehicleRows[] = new PlanningHeatmapVehicleData(
                id: $vehicle->id,
                licensePlate: $vehicle->license_plate,
                brand: $vehicle->brand,
                model: $vehicle->model,
                userType: $fiscal->vehicle_user_type,
                energy: $fiscal->energy_source,
                co2Method: $fiscal->homologation_method,
                co2Value: $fiscal->co2_wltp ?? $fiscal->co2_nedc,
                taxableHorsepower: $fiscal->taxable_horsepower,
                weeks: $weeks,
                daysTotal: array_sum($weeks),
                exitDate: $vehicle->exit_date?->toDateString(),
                weeksWithUnavailability: $this->collectWeeksWithUnavailability($vehicleUnavailabilities, $year),
                dailyRateCents: $pricing?->daily_rate_cents,
                weeklyRateCents: $pricing?->weekly_rate_cents,
                monthlyRateCents: $pricing?->monthly_rate_cents,
            );
        }

        $companyRows = $this->companies->findAllForHeatmap()
            ->map(static fn (Company $c): CompanyOptionData => new CompanyOptionData(
                id: $c->id,
                shortCode: $c->short_code,
                legalName: $c->legal_name,
                color: $c->color,
            ))
            ->values()
            ->all();

        return [
            'vehicles' => PlanningHeatmapVehicleData::collect($vehicleRows, DataCollection::class),
            'companies' => CompanyOptionData::collect($companyRows, DataCollection::class),
        ];
    }

    /**
     * Variante company-scoped de {@see buildHeatmap} pour la Vue
     * Entreprise (chantier P1). Le **chiffre cellule** ne reflète que
     * les jours utilisés par l'entreprise sélectionnée ; la **couleur**
     * reste pilotée par la densité globale (taux d'occupation toutes
     * entreprises confondues = signal de disponibilité du véhicule).
     *
     * @return array{
     *     vehicles: DataCollection<int, PlanningHeatmapCompanyVehicleData>,
     *     company: CompanyOptionData,
     *     companies: DataCollection<int, CompanyOptionData>,
     * }
     */
    public function buildHeatmapForCompany(int $year, Company $company): array
    {
        $weekDensityGlobal = $this->contracts->loadWeekDensity($year);
        $weekDensityForCompany = $this->contracts->loadWeekDensityForCompany($year, $company->id);

        $vehicles = $this->vehicles->findAllForHeatmap($year);
        $vehicleIds = $vehicles->pluck('id')->all();
        $unavailabilitiesByVehicleId = $this->contracts->loadUnavailabilitiesByVehicle($vehicleIds);
        $pricingsByVehicleId = $this->pricings->findForVehiclesAndYear($vehicleIds, $year);

        $weeksCount = IsoWeeks::CELLS_PER_YEAR;

        $vehicleRows = [];
        foreach ($vehicles as $vehicle) {
            $fiscal = $vehicle->fiscalCharacteristics->first();
            if ($fiscal === null) {
                continue;
            }

            $weeksGlobal = [];
            $weeksForCompany = [];
            for ($w = 1; $w <= $weeksCount; $w++) {
                $weeksGlobal[] = $weekDensityGlobal[$vehicle->id.'|'.$w] ?? 0;
                $weeksForCompany[] = $weekDensityForCompany[$vehicle->id.'|'.$w] ?? 0;
            }

            $vehicleUnavailabilities = $unavailabilitiesByVehicleId[$vehicle->id] ?? [];
            $pricing = $pricingsByVehicleId[$vehicle->id] ?? null;

            $vehicleRows[] = new PlanningHeatmapCompanyVehicleData(
                id: $vehicle->id,
                licensePlate: $vehicle->license_plate,
                brand: $vehicle->brand,
                model: $vehicle->model,
                userType: $fiscal->vehicle_user_type,
                energy: $fiscal->energy_source,
                co2Method: $fiscal->homologation_method,
                co2Value: $fiscal->co2_wltp ?? $fiscal->co2_nedc,
                taxableHorsepower: $fiscal->taxable_horsepower,
                weeksGlobal: $weeksGlobal,
                weeksForCompany: $weeksForCompany,
                daysTotalForCompany: array_sum($weeksForCompany),
                exitDate: $vehicle->exit_date?->toDateString(),
                weeksWithUnavailability: $this->collectWeeksWithUnavailability($vehicleUnavailabilities, $year),
                dailyRateCents: $pricing?->daily_rate_cents,
                weeklyRateCents: $pricing?->weekly_rate_cents,
                monthlyRateCents: $pricing?->monthly_rate_cents,
            );
        }

        $companyData = new CompanyOptionData(
            id: $company->id,
            shortCode: $company->short_code,
            legalName: $company->legal_name,
            color: $company->color,
        );

        $companyRows = $this->companies->findAllForHeatmap()
            ->map(static fn (Company $c): CompanyOptionData => new CompanyOptionData(
                id: $c->id,
                shortCode: $c->short_code,
                legalName: $c->legal_name,
                color: $c->color,
            ))
            ->values()
            ->all();

        return [
            'vehicles' => PlanningHeatmapCompanyVehicleData::collect($vehicleRows, DataCollection::class),
            'company' => $companyData,
            'companies' => CompanyOptionData::collect($companyRows, DataCollection::class),
        ];
    }

    /**
     * Map `vehicleId → PlanningHeatmapVehicleFullYearCostsData` pour la
     * heatmap planning. Coûts THÉORIQUES 100 % usage · indépendants du
     * scope entreprise.
     *
     * Source · {@see FleetFiscalAggregator::vehicleFullYearTaxBreakdown}
     * mémoïsée per-request. Cette méthode est servie en `Inertia::defer`
     * group « fast » côté controller · les valeurs « Taxe pleine » à
     * gauche de la heatmap apparaissent indépendamment de la taxe
     * annuelle due réelle qui prend plus de temps (cf.
     * {@see realCostsForVehicles}).
     *
     * @return array<int, PlanningHeatmapVehicleFullYearCostsData>
     */
    public function fullYearCostsForVehicles(int $year): array
    {
        $vehicles = $this->vehicles->findAllForHeatmap($year);

        // Prewarm batch VFC · économise les N+1 queries quand le cache
        // fiscal n'a pas encore d'entrée pour ces véhicules (cold).
        $this->aggregator->prewarmVfcSegmentsForVehicles($vehicles->all(), $year);

        $costs = [];
        foreach ($vehicles as $vehicle) {
            $fiscal = $vehicle->fiscalCharacteristics->first();
            if ($fiscal === null) {
                continue;
            }

            // Tolère une année hors règles fiscales codées (cf. doctrine
            // « données métier ⊥ règles fiscales »). On affiche 0/0 plutôt
            // que de crasher la heatmap.
            try {
                $fullYear = $this->aggregator->vehicleFullYearTaxBreakdown($vehicle, $year);
                $fullYearTax = $fullYear->total;
                $dailyTaxRate = $fullYear->daysInYear > 0
                    ? round($fullYear->total / $fullYear->daysInYear, 2, PHP_ROUND_HALF_UP)
                    : 0.0;
            } catch (FiscalCalculationException) {
                $fullYearTax = 0.0;
                $dailyTaxRate = 0.0;
            }

            $costs[$vehicle->id] = new PlanningHeatmapVehicleFullYearCostsData(
                fullYearTax: $fullYearTax,
                dailyTaxRate: $dailyTaxRate,
            );
        }

        return $costs;
    }

    /**
     * Map `vehicleId → PlanningHeatmapVehicleRealCostsData` pour la
     * heatmap planning, scoped (Vue Entreprise) ou global (Vue
     * d'ensemble) selon `$companyId`.
     *
     * Source · {@see FleetFiscalAggregator::vehicleAnnualTax} · NON
     * cachée (dépendrait des contrats + indispos + scope, invalidation
     * complexe). ~3-5 ms / véhicule = ~200 ms sur 64 véhicules. Servi
     * en `Inertia::defer` group « slow » · les valeurs « €XXXX · N j »
     * à droite de la heatmap arrivent indépendamment de la « Taxe
     * pleine » à gauche (cf. {@see fullYearCostsForVehicles}).
     *
     * @return array<int, PlanningHeatmapVehicleRealCostsData>
     */
    public function realCostsForVehicles(int $year, ?int $companyId = null): array
    {
        $vehicles = $this->vehicles->findAllForHeatmap($year);
        $vehicleIds = $vehicles->pluck('id')->all();
        $unavailabilitiesByVehicleId = $this->contracts->loadUnavailabilitiesByVehicle($vehicleIds);

        // Prewarm VFC batch · 1 query SQL au lieu du N+1 dans la boucle
        // `vehicleAnnualTax` ci-dessous.
        $this->aggregator->prewarmVfcSegmentsForVehicles($vehicles->all(), $year);

        $contractsByPair = $this->contracts->loadContractsByPair($year);
        if ($companyId !== null) {
            // Mirror de la logique de scope appliquée historiquement dans
            // `buildHeatmapForCompany` · on garde uniquement les paires
            // de l'entreprise demandée pour que `vehicleAnnualTax` ne
            // comptabilise que ses contrats.
            $contractsForCalc = new ContractsByPair(
                array_filter(
                    $contractsByPair->byPair,
                    static fn (string $key): bool => str_ends_with($key, '|'.$companyId),
                    ARRAY_FILTER_USE_KEY,
                ),
            );
        } else {
            $contractsForCalc = $contractsByPair;
        }

        $costs = [];
        foreach ($vehicles as $vehicle) {
            $fiscal = $vehicle->fiscalCharacteristics->first();
            if ($fiscal === null) {
                continue;
            }

            $vehicleUnavailabilities = $unavailabilitiesByVehicleId[$vehicle->id] ?? [];

            $annualTaxDue = $this->aggregator->vehicleAnnualTax(
                $vehicle,
                $contractsForCalc,
                $vehicleUnavailabilities,
                $year,
            );

            $costs[$vehicle->id] = new PlanningHeatmapVehicleRealCostsData(
                annualTaxDue: $annualTaxDue,
            );
        }

        return $costs;
    }

    /**
     * Totaux de loyer mensuel NET (post-réductions) pour une entreprise
     * sur une année · `array<int month [1..12], ?int totalCents>` ·
     * valeur `null` si au moins un véhicule présent sur le mois n'a pas
     * de tarif annuel saisi (signal UX explicite côté heatmap header).
     *
     * Servi en `Inertia::defer` group « rentals » côté
     * {@see App\Http\Controllers\User\Planning\PlanningController::companyIndex}.
     * Méthode dédiée slim au besoin planning (cf. doctrine
     * `chargement-strict-par-ecran`) · ne reconstruit pas la timeline
     * 12 mois lourde de {@see App\Services\Billing\BillingBreakdownService::byCompanyForYear}
     * (qui hydrate aussi factures émises + entrées détaillées).
     *
     * @return array<int, ?int> mois (1-12) → totalCents net post-discount, `null` si tarif manquant
     */
    public function monthlyRentalTotalsForCompany(int $year, int $companyId): array
    {
        $monthlyResults = $this->billingCalculator->calculateYear($companyId, $year);
        $discountIndex = $this->discountResolver->preloadForCompanyYear($companyId, $year);
        $monthlyResults = $this->discountApplier->applyToMonthlyResults($monthlyResults, $discountIndex);

        $totals = [];
        for ($month = 1; $month <= 12; $month++) {
            $result = $monthlyResults[$month];
            $totals[$month] = $result instanceof MissingPricingException
                ? null
                : $result->totalCents;
        }

        return $totals;
    }

    /**
     * Totaux de loyer mensuel NET cross-entreprises sur une année ·
     * agrégat fleet pour la vue d'ensemble du planning.
     *
     * **3 SQL fixes** quel que soit le nombre de companies impliquées ·
     *   1. contrats actifs croisant l'année (vehicle eager-loadé in-memory)
     *   2. vehicles by IDs (avec `exit_date` pour clipping ADR-0018)
     *   3. pricings batched `(vehicleIds × year)`
     * Plus 1 SQL pour les réductions actives multi-cies.
     *
     * Sémantique · pour chaque company, on calcule indépendamment via
     * `OptimalRateBreakdown` puis on applique les réductions de la
     * company. La somme cross-cies est faite en mémoire (zéro query).
     * **Mois avec tarif manquant partiel** · on additionne seulement les
     * cies sans manquement (cohérent avec `BillingBreakdownService::totalRecettesForYears`).
     *
     * Servi en `Inertia::defer` group « rentals » côté
     * {@see App\Http\Controllers\User\Planning\PlanningController::index}.
     *
     * @return array<int, int> mois (1-12) → totalCents net cross-cies (`0` si zéro contrat ce mois)
     */
    public function monthlyRentalTotalsForFleet(int $year): array
    {
        $contracts = $this->contractReader->findActiveForYearRange($year, $year);

        if ($contracts->isEmpty()) {
            return array_fill_keys(range(1, 12), 0);
        }

        $vehicleIdsSet = [];
        foreach ($contracts as $contract) {
            $vehicleIdsSet[(int) $contract->vehicle_id] = true;
        }
        $vehicleIdList = array_keys($vehicleIdsSet);

        $vehiclesById = $this->vehicles->findByIdsIndexed($vehicleIdList);

        foreach ($contracts as $contract) {
            $contract->setRelation('vehicle', $vehiclesById->get($contract->vehicle_id));
        }

        $pricingsByVehicle = $this->pricings->findForVehiclesAndYear($vehicleIdList, $year);

        $byCompany = [];
        foreach ($contracts as $contract) {
            $byCompany[(int) $contract->company_id][] = $contract;
        }

        $discountIndexByCompany = $this->discountResolver
            ->preloadForCompaniesYear(array_keys($byCompany), $year);

        $totals = array_fill_keys(range(1, 12), 0);

        foreach ($byCompany as $companyId => $companyContracts) {
            $monthlyResults = $this->billingCalculator->calculateYearWithPreloaded(
                $companyId,
                $year,
                $companyContracts,
                $pricingsByVehicle,
            );
            $index = $discountIndexByCompany[$companyId] ?? ResolvedDiscountIndex::empty();
            $monthlyResults = $this->discountApplier->applyToMonthlyResults($monthlyResults, $index);

            for ($month = 1; $month <= 12; $month++) {
                $result = $monthlyResults[$month];
                if (! $result instanceof MissingPricingException) {
                    $totals[$month] += $result->totalCents;
                }
            }
        }

        return $totals;
    }

    /**
     * Liste triée et dédoublonnée des numéros de semaines ISO (1-52) où
     * au moins un jour d'indisponibilité (tous types confondus) tombe
     * dans l'année fiscale demandée.
     *
     * Alimente la bordure rouge sur les cellules heatmap (ADR-0019 D5)
     * - visibilité immédiate de la cohabitation indispo↔contrat.
     *
     * @param  list<Unavailability>  $unavailabilities
     * @return list<int>
     */
    private function collectWeeksWithUnavailability(array $unavailabilities, int $year): array
    {
        $yearStart = CarbonImmutable::create($year, 1, 1)->startOfDay();
        $yearEnd = CarbonImmutable::create($year, 12, 31)->endOfDay();

        $weeks = [];
        foreach ($unavailabilities as $unavailability) {
            // Filtre indispos hors année (équivalent du WHERE SQL).
            if ($unavailability->start_date->greaterThan($yearEnd)) {
                continue;
            }
            if ($unavailability->end_date !== null && $unavailability->end_date->lessThan($yearStart)) {
                continue;
            }

            $start = $unavailability->start_date->greaterThan($yearStart)
                ? $unavailability->start_date
                : $yearStart;
            $end = $unavailability->end_date === null || $unavailability->end_date->greaterThan($yearEnd)
                ? $yearEnd
                : $unavailability->end_date;

            $cursor = $start;
            while ($cursor->lessThanOrEqualTo($end)) {
                if ($cursor->year === $year) {
                    $weeks[(int) $cursor->isoWeek] = true;
                }
                $cursor = $cursor->addDay();
            }
        }

        $list = array_keys($weeks);
        sort($list);

        return $list;
    }
}
