<?php

declare(strict_types=1);

namespace App\Services\Vehicle;

use App\Contracts\Repositories\User\Company\CompanyReadRepositoryInterface;
use App\Contracts\Repositories\User\Unavailability\UnavailabilityReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Data\User\Billing\MonthlyBillingBreakdownData;
use App\Data\User\Vehicle\VehicleCompanyUsageData;
use App\Data\User\Vehicle\VehicleFiscalCharacteristicsData;
use App\Data\User\Vehicle\VehicleFullYearTaxBreakdownData;
use App\Data\User\Vehicle\VehicleFullYearTaxSegmentData;
use App\Data\User\Vehicle\VehicleUsageStatsData;
use App\Data\User\Vehicle\VehicleWeekSegmentData;
use App\Data\User\Vehicle\VehicleWeekUsageData;
use App\DTO\Fiscal\ContractsByPair;
use App\Exceptions\Fiscal\FiscalCalculationException;
use App\Models\Company;
use App\Models\Unavailability;
use App\Models\Vehicle;
use App\Services\Billing\BillingBreakdownService;
use App\Services\Contract\ContractQueryService;
use App\Services\Fiscal\FleetFiscalAggregator;
use App\Services\Shared\Fiscal\FiscalYearContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Agrégats annuels par-véhicule pour les onglets Show et endpoints JSON
 * lazy (extrait de `VehicleQueryService` pour respecter SRP · Lot 4 D09 /
 * F-14-003).
 *
 * Regroupe les 3 façades par-année consommées par la fiche véhicule ·
 *   - `billingForYear` · onglet Billing (récap mensuel facturation)
 *   - `usageStatsForYear` · endpoint JSON `useYearLazy` (carte
 *      Utilisation & Répartition de l'onglet Vue d'ensemble)
 *   - `fullYearBreakdownForYear` · endpoint JSON onglet Fiscalité
 *
 * Centralise également les helpers privés qui composent
 * `VehicleUsageStatsData` (timeline hebdomadaire, breakdown par company,
 * fallbacks pour années sans règles codées) · réutilisés par
 * `VehicleDetailService::findVehicleData` via `buildUsageStats()`.
 */
final class VehicleAggregatesService
{
    public function __construct(
        private readonly VehicleReadRepositoryInterface $vehicles,
        private readonly CompanyReadRepositoryInterface $companies,
        private readonly UnavailabilityReadRepositoryInterface $unavailabilityRepo,
        private readonly ContractQueryService $contracts,
        private readonly FleetFiscalAggregator $aggregator,
        private readonly FiscalYearContext $yearContext,
        private readonly BillingBreakdownService $billingBreakdown,
    ) {}

    /**
     * Récap mensuel facturation pour un véhicule et une année donnée
     * (Phase 14.D V1.2). Sépare l'agrégation `BillingBreakdownService`
     * du flot principal `findVehicleData()` pour permettre un sélecteur
     * d'année indépendant côté UI.
     */
    public function billingForYear(int $vehicleId, int $year): MonthlyBillingBreakdownData
    {
        return $this->billingBreakdown->byVehicleForYear($vehicleId, $year);
    }

    /**
     * Endpoint lazy : recalcule `VehicleUsageStatsData` pour une année
     * arbitraire du scope. Appelé par `useYearLazy` côté front quand
     * l'utilisateur change l'année du sélecteur de la carte
     * Utilisation & Répartition (onglet Vue d'ensemble).
     *
     * Tolère une année sans règles fiscales codées : Timeline et jours
     * bruts intacts, chiffres taxes à 0 + breakdown FullYear neutre.
     */
    public function usageStatsForYear(int $vehicleId, int $year): VehicleUsageStatsData
    {
        $vehicle = $this->vehicles->findByIdWithFiscalHistory($vehicleId);
        $unavailabilityModels = $this->unavailabilityRepo->findForVehicle($vehicle->id);

        return $this->buildUsageStats($vehicle, $year, $unavailabilityModels);
    }

    /**
     * Endpoint lazy : recalcule `VehicleFullYearTaxBreakdownData` pour
     * une année arbitraire du scope. Appelé par l'onglet Fiscalité au
     * changement d'année du sélecteur dédié.
     *
     * Si l'année n'a pas de règles fiscales codées, retourne un DTO
     * neutre (tarifs 0 + message « Règles non implémentées »).
     */
    public function fullYearBreakdownForYear(int $vehicleId, int $year): VehicleFullYearTaxBreakdownData
    {
        $vehicle = $this->vehicles->findByIdWithFiscalHistory($vehicleId);

        try {
            return $this->aggregator->vehicleFullYearTaxBreakdown($vehicle, $year);
        } catch (FiscalCalculationException) {
            return $this->emptyFullYearBreakdown($vehicle, $year);
        }
    }

    /**
     * Composition publique de `VehicleUsageStatsData` pour le mount
     * initial de la page Show (consommée par `VehicleDetailService::findVehicleData`).
     *
     * Le call-site Detail charge déjà le véhicule et les indispos en bulk
     * pour partager avec les autres calculs ; on les passe en paramètres
     * plutôt que de les ré-charger ici (économie 2 SQL).
     *
     * @param  Collection<int, Unavailability>  $unavailabilityModels
     */
    public function buildUsageStats(Vehicle $vehicle, int $year, Collection $unavailabilityModels): VehicleUsageStatsData
    {
        $daysInYear = $this->yearContext->daysInYear($year);
        $contractsByPair = $this->contracts->loadContractsByPairForVehicle($vehicle->id, $year);
        $vehicleUnavailabilities = $unavailabilityModels->all();
        $weeklyMap = $this->contracts->loadVehicleWeeklyBreakdown($vehicle->id, $year);
        $unavailabilityDaysByWeek = $this->computeUnavailabilityDaysByWeek($unavailabilityModels, $year);

        // Calcul fiscal · encadré pour tolérer une année hors registry
        // (doctrine « données métier ⊥ règles fiscales » : la Timeline et
        // les jours bruts restent toujours affichables, seuls les
        // chiffres de taxe tombent à 0 + breakdown FullYear neutre).
        try {
            $breakdown = $this->aggregator->vehicleAnnualTaxBreakdownByCompany(
                $vehicle,
                $contractsByPair,
                $vehicleUnavailabilities,
                $year,
            );
            $fullYearBreakdown = $this->aggregator->vehicleFullYearTaxBreakdown($vehicle, $year);
        } catch (FiscalCalculationException) {
            $breakdown = $this->fallbackBreakdownByCompany($contractsByPair, $vehicle->id);
            $fullYearBreakdown = $this->emptyFullYearBreakdown($vehicle, $year);
        }

        $companyIds = $this->collectCompanyIds($breakdown, $weeklyMap);
        $companiesById = $this->companies->findByIdsIndexed($companyIds);

        usort(
            $breakdown,
            static fn (array $a, array $b): int => $b['days'] <=> $a['days'],
        );

        $companies = [];
        $totalDays = 0;
        $totalTax = 0.0;
        foreach ($breakdown as $row) {
            $company = $companiesById->get($row['companyId']);
            if ($company === null) {
                continue;
            }
            $proratoPercent = $daysInYear > 0
                ? round($row['days'] / $daysInYear * 100, 1)
                : 0.0;

            $companies[] = new VehicleCompanyUsageData(
                companyId: $company->id,
                shortCode: $company->short_code,
                legalName: $company->legal_name,
                color: $company->color,
                daysUsed: $row['days'],
                proratoPercent: $proratoPercent,
                taxCo2: $row['taxCo2'],
                taxPollutants: $row['taxPollutants'],
                taxTotal: $row['taxTotal'],
            );
            $totalDays += $row['days'];
            $totalTax += $row['taxTotal'];
        }

        return new VehicleUsageStatsData(
            fiscalYear: $year,
            daysInYear: $daysInYear,
            daysUsedThisYear: $totalDays,
            actualTaxThisYear: round($totalTax, 2, PHP_ROUND_HALF_UP),
            fullYearTax: $fullYearBreakdown->total,
            dailyTaxRate: $daysInYear > 0
                ? round($fullYearBreakdown->total / $daysInYear, 2, PHP_ROUND_HALF_UP)
                : 0.0,
            companies: $companies,
            weeklyBreakdown: $this->buildWeeklyBreakdown($weeklyMap, $unavailabilityDaysByWeek, $companiesById, $year),
            fullYearTaxBreakdown: $fullYearBreakdown,
        );
    }

    /**
     * Compose un breakdown par entreprise à partir des contrats seuls
     * (jours uniquement, sans calcul fiscal). Utilisé en fallback quand
     * le pipeline fiscal n'est pas disponible pour l'année · la colonne
     * « Jours » reste informative, les colonnes Tax CO₂/Polluants/Total
     * sont à 0.
     *
     * @return list<array{companyId: int, days: int, taxCo2: float, taxPollutants: float, taxTotal: float}>
     */
    private function fallbackBreakdownByCompany(ContractsByPair $contractsByPair, int $vehicleId): array
    {
        $rows = [];
        foreach ($contractsByPair->pairsForVehicle($vehicleId) as $companyId => $pairContracts) {
            $days = 0;
            foreach ($pairContracts as $contract) {
                $days += $contract->countDaysInYear($contract->start_date->year);
            }
            $rows[] = [
                'companyId' => $companyId,
                'days' => $days,
                'taxCo2' => 0.0,
                'taxPollutants' => 0.0,
                'taxTotal' => 0.0,
            ];
        }

        return $rows;
    }

    /**
     * DTO `VehicleFullYearTaxBreakdownData` neutre · tarifs à 0 et
     * messages explicites pour l'UI quand l'année n'a pas de règles
     * fiscales codées. Les enums `co2Method` / `pollutantCategory` sont
     * pris du current VFC du véhicule (ou défaut WLTP/Category1).
     */
    private function emptyFullYearBreakdown(Vehicle $vehicle, int $year): VehicleFullYearTaxBreakdownData
    {
        $current = $vehicle->fiscalCharacteristics
            ->firstWhere(static fn ($vfc): bool => $vfc->effective_to === null);

        $message = sprintf('Règles fiscales %d non implémentées.', $year);

        // Année non supportée → pas d'exécution du pipeline, pas de
        // segments calculés. On expose la VFC actuelle (si elle existe)
        // sous forme d'un segment unique couvrant l'année avec tarifs
        // et dûs à 0, pour conserver la traçabilité dans l'UI sans
        // mentir sur le calcul (qui n'a pas eu lieu).
        $taxSegments = [];
        if ($current !== null) {
            $taxSegments[] = new VehicleFullYearTaxSegmentData(
                effectiveFromInYear: sprintf('%04d-01-01', $year),
                effectiveToInYear: sprintf('%04d-12-31', $year),
                daysInSegment: $this->yearContext->daysInYear($year),
                vfc: VehicleFiscalCharacteristicsData::fromModel($current),
                co2Method: $current->homologation_method,
                co2FullYearTariff: 0.0,
                co2Explanation: $message,
                co2Due: 0.0,
                pollutantCategory: $current->pollutant_category,
                pollutantsFullYearTariff: 0.0,
                pollutantsExplanation: $message,
                pollutantsDue: 0.0,
                appliedExemptions: [],
                appliedRuleCodes: [],
            );
        }

        return new VehicleFullYearTaxBreakdownData(
            daysInYear: $this->yearContext->daysInYear($year),
            total: 0.0,
            appliedExemptions: [],
            appliedRuleCodes: [],
            appliedRules: [],
            taxSegments: $taxSegments,
        );
    }

    /**
     * Collecte tous les companyIds référencés par le breakdown
     * fiscal et la timeline hebdo. Garantit qu'aucune lookup
     * Eloquent n'est manquante (ex. semaine seedée mais 0 jour
     * cumul filtré par fourrière).
     *
     * @param  list<array{companyId: int, days: int, taxCo2: float, taxPollutants: float, taxTotal: float}>  $breakdown
     * @param  array<int, array<int, int>>  $weeklyMap
     * @return list<int>
     */
    private function collectCompanyIds(array $breakdown, array $weeklyMap): array
    {
        $ids = [];
        foreach ($breakdown as $row) {
            $ids[$row['companyId']] = true;
        }
        foreach ($weeklyMap as $companies) {
            foreach (array_keys($companies) as $companyId) {
                $ids[$companyId] = true;
            }
        }

        return array_keys($ids);
    }

    /**
     * Compose la liste des 52-53 entrées weekly (1 par semaine ISO
     * de l'année) pour la timeline visuelle. Les semaines vides
     * sont matérialisées avec `segments = []` et `totalDays = 0`.
     *
     * Les jours d'indispo sont splittés en deux dimensions :
     * `reductiveUnavailabilityDays` (R-2024-008) et
     * `nonReductiveUnavailabilityDays` · la timeline les rend en
     * overlay distinct (rose vs slate). ADR-0019 autorisant la
     * cohabitation indispo↔contrat, on **ne clamp plus** à
     * `7 - totalDays` : l'utilisateur doit voir une indispo de
     * fourrière même quand le contrat couvre toute la semaine.
     *
     * @param  array<int, array<int, int>>  $weeklyMap  weekNumber → companyId → days
     * @param  array<int, array{reductive: int, nonReductive: int}>  $unavailabilityDaysByWeek
     * @param  Collection<int, Company>  $companiesById
     * @return list<VehicleWeekUsageData>
     */
    private function buildWeeklyBreakdown(
        array $weeklyMap,
        array $unavailabilityDaysByWeek,
        Collection $companiesById,
        int $year,
    ): array {
        $weeksInYear = (int) Carbon::create($year, 12, 28)->isoWeeksInYear;

        $rows = [];
        for ($week = 1; $week <= $weeksInYear; $week++) {
            $segments = [];
            $totalDays = 0;
            foreach (($weeklyMap[$week] ?? []) as $companyId => $days) {
                $company = $companiesById->get($companyId);
                if ($company === null) {
                    continue;
                }
                $segments[] = new VehicleWeekSegmentData(
                    companyId: $company->id,
                    shortCode: $company->short_code,
                    color: $company->color,
                    days: $days,
                );
                $totalDays += $days;
            }
            // Tri stable par companyId pour rendu déterministe
            usort(
                $segments,
                static fn (VehicleWeekSegmentData $a, VehicleWeekSegmentData $b): int => $a->companyId <=> $b->companyId,
            );

            $weekUnavailability = $unavailabilityDaysByWeek[$week] ?? ['reductive' => 0, 'nonReductive' => 0];

            $rows[] = new VehicleWeekUsageData(
                weekNumber: $week,
                segments: $segments,
                totalDays: $totalDays,
                reductiveUnavailabilityDays: $weekUnavailability['reductive'],
                nonReductiveUnavailabilityDays: $weekUnavailability['nonReductive'],
            );
        }

        return $rows;
    }

    /**
     * Comptage `weekNumber → {reductive, nonReductive}` à partir de la
     * Collection brute déjà chargée. Une même date couverte par 2 indispos
     * de types différents (cas autorisé par ADR-0019) est comptée une
     * seule fois, avec priorité au caractère réducteur (impact fiscal R-2024-008
     * étant l'info la plus parlante côté UI).
     *
     * @param  Collection<int, Unavailability>  $unavailabilityModels
     * @return array<int, array{reductive: int, nonReductive: int}> weekNumber → totaux par type
     */
    private function computeUnavailabilityDaysByWeek(Collection $unavailabilityModels, int $year): array
    {
        $yearStart = Carbon::create($year, 1, 1)->startOfDay();
        $yearEnd = Carbon::create($year, 12, 31)->endOfDay();

        /** @var array<int, array<string, 'reductive'|'nonReductive'>> $byWeekDays */
        $byWeekDays = [];
        foreach ($unavailabilityModels as $row) {
            // Filtre indispo croisant l'année (équivalent du WHERE SQL).
            if ($row->start_date->greaterThan($yearEnd)) {
                continue;
            }
            if ($row->end_date !== null && $row->end_date->lessThan($yearStart)) {
                continue;
            }

            $isReductive = $row->type->isFiscallyReductive();
            $start = $row->start_date->greaterThan($yearStart) ? $row->start_date : $yearStart;
            $end = $row->end_date === null || $row->end_date->greaterThan($yearEnd)
                ? $yearEnd
                : $row->end_date;

            $cursor = $start;
            while ($cursor->lessThanOrEqualTo($end)) {
                if ($cursor->year === $year) {
                    $week = (int) $cursor->isoWeek;
                    $date = $cursor->toDateString();
                    $existing = $byWeekDays[$week][$date] ?? null;

                    // Priorité réductrice (impact fiscal) sur non-réductrice.
                    if ($existing !== 'reductive') {
                        $byWeekDays[$week][$date] = $isReductive ? 'reductive' : 'nonReductive';
                    }
                }
                $cursor = $cursor->addDay();
            }
        }

        $byWeek = [];
        foreach ($byWeekDays as $week => $dates) {
            $reductive = 0;
            $nonReductive = 0;
            foreach ($dates as $kind) {
                if ($kind === 'reductive') {
                    $reductive++;
                } else {
                    $nonReductive++;
                }
            }
            $byWeek[$week] = ['reductive' => $reductive, 'nonReductive' => $nonReductive];
        }
        ksort($byWeek);

        return $byWeek;
    }
}
