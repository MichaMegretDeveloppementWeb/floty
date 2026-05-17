<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Contracts\Repositories\User\Contract\ContractReadRepositoryInterface;
use App\Contracts\Repositories\User\Invoice\InvoiceReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleYearlyPricingReadRepositoryInterface;
use App\Data\User\Billing\ContractBillingBreakdownData;
use App\Data\User\Billing\ContractBillingMonthData;
use App\Data\User\Billing\MonthlyBillingBreakdownData;
use App\Data\User\Billing\MonthlyBreakdownEntryData;
use App\Exceptions\Billing\MissingPricingException;
use App\Models\Contract;

/**
 * Compose les récaps mensuels 12-mois consommés par les fiches Show
 * Vehicle / Company (Phase 14.D V1.2).
 *
 * **Doctrine** : sur 12 mois, on accumule les facturations « cas par
 * cas » via {@see BillingCalculator}. Si un mois lève
 * `MissingPricingException`, on **n'interrompt pas** l'aller-retour ;
 * on marque ce mois comme `hasMissingPricing = true` et on continue.
 * Le récap reste utilisable même si l'utilisateur n'a renseigné qu'une
 * partie des tarifs.
 */
final class BillingBreakdownService
{
    /**
     * Cache mémoire intra-instance des récaps 12-mois par entreprise ·
     * indexé par `"{companyId}|{year}"`. Hot path Dashboard ·
     * `DashboardStatsService::computePeriodMetrics` itère 8 ans × 15
     * entreprises = 120 appels par mount Dashboard, dont la grande
     * majorité touchent les mêmes couples (companyId, year) à travers
     * les services consommateurs. S1.2 du plan optim perf 2026-05-15.
     *
     * **Sécurité** · le DTO `MonthlyBillingBreakdownData` est calculé
     * de manière déterministe sur `(companyId, year)` à partir des
     * tarifs annuels et factures existantes (state DB read-only). Pas
     * de mutation entre 2 appels dans une même requête HTTP. Singleton
     * scoped per-request (cf. `AppServiceProvider`).
     *
     * @var array<string, MonthlyBillingBreakdownData>
     */
    private array $byCompanyForYearCache = [];

    /** @var array<string, MonthlyBillingBreakdownData> */
    private array $byVehicleForYearCache = [];

    /**
     * Cache mémoire intra-instance des totaux annuels cross-cies par
     * `(year)` calculés via {@see totalRecettesForYears} · permet à
     * `Dashboard computeKpisRecettes` (year + Y-1) puis `computeHistory`
     * (8 ans · defer wave séparé, instance différente) de bénéficier
     * de la mémoïsation si on les rappelle dans la même défer wave.
     *
     * @var array<int, int>
     */
    private array $totalRecettesForYearsCache = [];

    public function __construct(
        private readonly BillingCalculator $calculator,
        private readonly VehicleYearlyPricingReadRepositoryInterface $pricingRepository,
        private readonly InvoiceReadRepositoryInterface $invoiceRepository,
        private readonly ContractReadRepositoryInterface $contractRepository,
        private readonly VehicleReadRepositoryInterface $vehicleRepository,
    ) {}

    /**
     * Récap 12-mois pour la **fiche entreprise** : agrégat tous véhicules
     * utilisés × tarifs facturés. Un mois est marqué « tarif manquant »
     * dès qu'un seul véhicule présent ce mois-là n'a pas de tarif annuel.
     */
    public function byCompanyForYear(int $companyId, int $year): MonthlyBillingBreakdownData
    {
        $key = $companyId.'|'.$year;

        return $this->byCompanyForYearCache[$key] ??= $this->computeByCompanyForYear($companyId, $year);
    }

    private function computeByCompanyForYear(int $companyId, int $year): MonthlyBillingBreakdownData
    {
        $entries = [];
        $totalDays = 0;
        $totalCents = 0;
        $hasAnyMissing = false;

        // Lookup unique des factures déjà émises pour le couple
        // (entreprise × année), indexées par mois · sert au bouton UI
        // « Voir #YYYY-MM-NNNN » sans payer 12 lookups.
        $existingInvoices = $this->invoiceRepository->findExistingByMonthForCompanyYear($companyId, $year);

        // Batch des 12 mois en 2 SQL totales (contrats année + pricings
        // batched) · au lieu de 12× findForCompanyInPeriod + N pricings.
        $monthlyResults = $this->calculator->calculateYear($companyId, $year);

        for ($month = 1; $month <= 12; $month++) {
            $existing = $existingInvoices[$month] ?? null;
            $result = $monthlyResults[$month];

            if ($result instanceof MissingPricingException) {
                // Mois bloqué : on conserve daysUsed à 0 pour ne pas
                // induire en erreur. La présence du flag suffit pour le
                // tooltip « renseignez le tarif <X> sur la fiche
                // véhicule » côté UI.
                $entries[] = new MonthlyBreakdownEntryData(
                    month: $month,
                    daysUsed: 0,
                    totalCents: null,
                    hasMissingPricing: true,
                    existingInvoiceId: $existing['id'] ?? null,
                    existingInvoiceNumber: $existing['invoiceNumber'] ?? null,
                    invoicedDaysUsed: $existing['invoicedDaysUsed'] ?? null,
                    invoicedTotalCents: $existing['totalHtCents'] ?? null,
                );
                $hasAnyMissing = true;

                continue;
            }

            $monthDays = array_sum(array_map(
                static fn ($l) => $l->daysUsed,
                $result->lines,
            ));
            $entries[] = new MonthlyBreakdownEntryData(
                month: $month,
                daysUsed: $monthDays,
                totalCents: $result->totalCents,
                hasMissingPricing: false,
                existingInvoiceId: $existing['id'] ?? null,
                existingInvoiceNumber: $existing['invoiceNumber'] ?? null,
                invoicedDaysUsed: $existing['invoicedDaysUsed'] ?? null,
                invoicedTotalCents: $existing['totalHtCents'] ?? null,
            );
            $totalDays += $monthDays;
            $totalCents += $result->totalCents;
        }

        return new MonthlyBillingBreakdownData(
            year: $year,
            entries: $entries,
            yearTotalDaysUsed: $totalDays,
            yearTotalCents: $hasAnyMissing ? null : $totalCents,
            // T11 / E.17 : total partiel (mois sans missing pricing) toujours peuplé.
            yearTotalCentsPartial: $totalCents,
            hasAnyMissingPricing: $hasAnyMissing,
        );
    }

    /**
     * Totaux annuels recettes HT cross-toutes-cies pour plein scope
     * Dashboard (chantier perf Dashboard 2026-05-17). **3 queries SQL
     * fixes** quel que soit le nombre de companies × years ·
     *   1. contrats actifs croisant `[min(years), max(years)]`
     *   2. vehicles by IDs (avec `exit_date` pour clipping ADR-0018)
     *   3. pricings batched `(vehicleIds × years)`
     *
     * Remplace les N×M appels à {@see byCompanyForYear} qui faisaient
     * 3 queries chacun (contrats + pricings + invoices), soit jusqu'à
     * 120+ queries pour Dashboard 15 cies × 2 ans, alors qu'on n'a
     * besoin que de la somme `yearTotalCentsPartial`.
     *
     * Sémantique strictement identique à
     * `Σ_cies byCompanyForYear(cie, year)->yearTotalCentsPartial` ·
     * couvert par le test
     * {@see Tests\Unit\Services\Billing\BillingBreakdownServiceTest::total_recettes_for_years_equivalent_to_sum_by_company}
     * (doctrine `optimisations-conditionnelles.md` stratégie 2).
     *
     * Mémoïsation per-request via `$totalRecettesForYearsCache` ·
     * si appelé deux fois avec un overlap d'années dans la même défer
     * wave, les couples (year) déjà calculés ne sont pas recalculés.
     *
     * @param  list<int>  $years
     * @return array<int, int> year → totalCentsPartial cross-toutes-cies
     */
    public function totalRecettesForYears(array $years): array
    {
        if ($years === []) {
            return [];
        }

        // Mémoïsation · ne recalcule que les années manquantes.
        $missingYears = array_values(array_filter(
            $years,
            fn (int $y): bool => ! array_key_exists($y, $this->totalRecettesForYearsCache),
        ));

        if ($missingYears !== []) {
            $computed = $this->computeTotalRecettesForYears($missingYears);
            foreach ($computed as $y => $total) {
                $this->totalRecettesForYearsCache[$y] = $total;
            }
        }

        $result = [];
        foreach ($years as $y) {
            $result[$y] = $this->totalRecettesForYearsCache[$y];
        }

        return $result;
    }

    /**
     * @param  list<int>  $years
     * @return array<int, int>
     */
    private function computeTotalRecettesForYears(array $years): array
    {
        $minYear = min($years);
        $maxYear = max($years);

        // 1 query · tous les contrats croisant le range (vehicle_id +
        // company_id + dates). Tri `vehicle_id, start_date`.
        $contracts = $this->contractRepository->findActiveForYearRange($minYear, $maxYear);

        if ($contracts->isEmpty()) {
            return array_fill_keys($years, 0);
        }

        // Collecte les vehicleIds distincts du scope.
        $vehicleIdsSet = [];
        foreach ($contracts as $contract) {
            $vehicleIdsSet[(int) $contract->vehicle_id] = true;
        }
        $vehicleIdList = array_keys($vehicleIdsSet);

        // 1 query · vehicles indexés (avec exit_date pour le clipping
        // ADR-0018 utilisé par `expandContractsByKey`).
        $vehiclesById = $this->vehicleRepository->findByIdsIndexed($vehicleIdList);

        // Hydrate la relation `vehicle` sur chaque contrat in-memory ·
        // évite N+1 dans `expandContractsByKey` qui consomme
        // `$contract->vehicle?->exit_date`.
        foreach ($contracts as $contract) {
            $contract->setRelation('vehicle', $vehiclesById->get($contract->vehicle_id));
        }

        // 1 query · pricings batched `(vehicleIds × years)`.
        $pricingsByYearByVehicle = $this->pricingRepository->findForVehiclesAndYears(
            $vehicleIdList,
            $years,
        );

        // Group contracts par (companyId, year) en mémoire (0 query).
        // Un contrat multi-année est dispatché dans chacune des années
        // qu'il couvre ∩ $years.
        $byCompanyByYear = [];
        foreach ($contracts as $contract) {
            $startYear = (int) $contract->start_date->year;
            $endYear = (int) $contract->end_date->year;
            foreach ($years as $year) {
                if ($year < $startYear || $year > $endYear) {
                    continue;
                }
                $byCompanyByYear[(int) $contract->company_id][$year][] = $contract;
            }
        }

        // Compute totals en mémoire via BillingCalculator (0 query).
        $totals = array_fill_keys($years, 0);
        foreach ($byCompanyByYear as $companyId => $byYear) {
            foreach ($byYear as $year => $companyYearContracts) {
                $pricingsForYear = $pricingsByYearByVehicle[$year] ?? [];
                $monthlyResults = $this->calculator->calculateYearWithPreloaded(
                    $companyId,
                    $year,
                    $companyYearContracts,
                    $pricingsForYear,
                );
                for ($month = 1; $month <= 12; $month++) {
                    $result = $monthlyResults[$month];
                    if (! $result instanceof MissingPricingException) {
                        $totals[$year] += $result->totalCents;
                    }
                }
            }
        }

        return $totals;
    }

    /**
     * Récap 12-mois pour la **fiche véhicule** : agrégat de la recette
     * mensuelle cross-entreprises pour ce véhicule. Pas de combinaison
     * tarifaire mutualisée : chaque entreprise est facturée séparément
     * (cf. {@see BillingCalculator::calculateForVehicleAndMonth}).
     */
    public function byVehicleForYear(int $vehicleId, int $year): MonthlyBillingBreakdownData
    {
        $key = $vehicleId.'|'.$year;

        return $this->byVehicleForYearCache[$key] ??= $this->computeByVehicleForYear($vehicleId, $year);
    }

    private function computeByVehicleForYear(int $vehicleId, int $year): MonthlyBillingBreakdownData
    {
        $entries = [];
        $totalDays = 0;
        $totalCents = 0;
        $hasAnyMissing = false;

        for ($month = 1; $month <= 12; $month++) {
            try {
                $result = $this->calculator->calculateForVehicleAndMonth($vehicleId, $year, $month);
                $entries[] = new MonthlyBreakdownEntryData(
                    month: $month,
                    daysUsed: $result['daysUsed'],
                    totalCents: $result['totalCents'],
                    hasMissingPricing: false,
                );
                $totalDays += $result['daysUsed'];
                $totalCents += $result['totalCents'];
            } catch (MissingPricingException) {
                $entries[] = new MonthlyBreakdownEntryData(
                    month: $month,
                    daysUsed: 0,
                    totalCents: null,
                    hasMissingPricing: true,
                );
                $hasAnyMissing = true;
            }
        }

        return new MonthlyBillingBreakdownData(
            year: $year,
            entries: $entries,
            yearTotalDaysUsed: $totalDays,
            yearTotalCents: $hasAnyMissing ? null : $totalCents,
            // T11 / E.17 : total partiel (mois sans missing pricing) toujours peuplé.
            yearTotalCentsPartial: $totalCents,
            hasAnyMissingPricing: $hasAnyMissing,
        );
    }

    /**
     * Récap facturation **contrat-isolé** : pour chaque mois civil que
     * le contrat couvre, calcule le coût en isolation (jours du contrat
     * × tarif du véhicule pour l'année).
     *
     * **Caveat sémantique** : si le véhicule a plusieurs contrats sur
     * le même mois pour la même entreprise, le coût isolé peut différer
     * de la facture mensuelle réelle (qui consolide via OptimalRateBreakdown
     * sur les jours unionés). C'est une approximation acceptée pour la
     * fiche contrat · la facture reste la source de vérité.
     */
    public function byContract(Contract $contract): ContractBillingBreakdownData
    {
        $start = $contract->start_date->toImmutable();
        $end = $contract->end_date->toImmutable();

        $months = [];
        $totalDays = 0;
        $totalCents = 0;
        $hasAnyMissing = false;

        // Itère mois par mois entre start et end (inclus).
        $cursor = $start->startOfMonth();
        $endMonth = $end->startOfMonth();

        while (! $cursor->isAfter($endMonth)) {
            $monthStart = $cursor;
            $monthEnd = $cursor->endOfMonth();

            // Intersection contrat ∩ mois.
            $clipStart = $start->isAfter($monthStart) ? $start : $monthStart;
            $clipEnd = $end->isBefore($monthEnd) ? $end : $monthEnd;

            $daysInMonth = (int) $clipStart->diffInDays($clipEnd) + 1;

            $year = $cursor->year;
            $pricing = $this->pricingRepository->findForVehicleAndYear($contract->vehicle_id, $year);

            if ($pricing === null) {
                $months[] = new ContractBillingMonthData(
                    year: $year,
                    month: $cursor->month,
                    daysInMonth: $daysInMonth,
                    totalCents: null,
                    hasMissingPricing: true,
                );
                $totalDays += $daysInMonth;
                $hasAnyMissing = true;
            } else {
                $breakdown = OptimalRateBreakdown::compute(
                    daysUsed: $daysInMonth,
                    dailyCents: $pricing->daily_rate_cents,
                    weeklyCents: $pricing->weekly_rate_cents,
                    monthlyCents: $pricing->monthly_rate_cents,
                );

                $months[] = new ContractBillingMonthData(
                    year: $year,
                    month: $cursor->month,
                    daysInMonth: $daysInMonth,
                    totalCents: $breakdown->totalCents,
                    hasMissingPricing: false,
                );
                $totalDays += $daysInMonth;
                $totalCents += $breakdown->totalCents;
            }

            $cursor = $cursor->addMonth();
        }

        return new ContractBillingBreakdownData(
            months: $months,
            totalDaysUsed: $totalDays,
            totalCents: $hasAnyMissing ? null : $totalCents,
            hasAnyMissingPricing: $hasAnyMissing,
        );
    }
}
