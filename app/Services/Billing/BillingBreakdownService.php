<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Contracts\Repositories\User\Invoice\InvoiceReadRepositoryInterface;
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

    public function __construct(
        private readonly BillingCalculator $calculator,
        private readonly VehicleYearlyPricingReadRepositoryInterface $pricingRepository,
        private readonly InvoiceReadRepositoryInterface $invoiceRepository,
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
