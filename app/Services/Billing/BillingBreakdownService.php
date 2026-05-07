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
use Carbon\CarbonImmutable;

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
final readonly class BillingBreakdownService
{
    public function __construct(
        private BillingCalculator $calculator,
        private VehicleYearlyPricingReadRepositoryInterface $pricingRepository,
        private InvoiceReadRepositoryInterface $invoiceRepository,
    ) {}

    /**
     * Récap 12-mois pour la **fiche entreprise** : agrégat tous véhicules
     * utilisés × tarifs facturés. Un mois est marqué « tarif manquant »
     * dès qu'un seul véhicule présent ce mois-là n'a pas de tarif annuel.
     */
    public function byCompanyForYear(int $companyId, int $year): MonthlyBillingBreakdownData
    {
        $entries = [];
        $totalDays = 0;
        $totalCents = 0;
        $hasAnyMissing = false;

        // Lookup unique des factures déjà émises pour le couple
        // (entreprise × année), indexées par mois · sert au bouton UI
        // « Voir #YYYY-MM-NNNN » sans payer 12 lookups.
        $existingInvoices = $this->invoiceRepository->findExistingByMonthForCompanyYear($companyId, $year);

        for ($month = 1; $month <= 12; $month++) {
            $existing = $existingInvoices[$month] ?? null;

            try {
                $result = $this->calculator->calculate($companyId, $year, $month);
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
            } catch (MissingPricingException) {
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
     * Récap 12-mois pour la **fiche véhicule** : agrégat de la recette
     * mensuelle cross-entreprises pour ce véhicule. Pas de combinaison
     * tarifaire mutualisée : chaque entreprise est facturée séparément
     * (cf. {@see BillingCalculator::calculateForVehicleAndMonth}).
     */
    public function byVehicleForYear(int $vehicleId, int $year): MonthlyBillingBreakdownData
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
        $start = CarbonImmutable::parse($contract->start_date->toDateString());
        $end = CarbonImmutable::parse($contract->end_date->toDateString());

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
