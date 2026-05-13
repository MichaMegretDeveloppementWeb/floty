<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Data\User\Billing\PendingInvoiceYearData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Résout, pour une entreprise donnée, la liste des années où il reste
 * des factures mensuelles à générer (Phase D5.10.S).
 *
 * Symétrique de {@see App\Services\Fiscal\Declaration\PendingDeclarationsResolver}
 * côté facturation : alimente l'encart « À faire » de l'onglet Vue
 * d'ensemble de la fiche entreprise.
 *
 * Granularité : 1 entrée par année (pas par mois). Le détail mois par
 * mois reste accessible via l'onglet Facturation. L'encart se contente
 * de signaler « N factures à générer pour YYYY ».
 *
 * Coût : N×12 appels à {@see BillingCalculator::calculate} via le service
 * de breakdown. Acceptable pour la doctrine V1.2 dev local (faible
 * volumétrie · les calculs sont déjà cached intra-instance du pipeline
 * fiscal). À matérialiser plus tard si volumétrie ↑.
 */
final readonly class PendingInvoicesResolver
{
    public function __construct(
        private BillingBreakdownService $breakdown,
    ) {}

    /**
     * @return list<PendingInvoiceYearData>
     */
    public function pendingForCompany(int $companyId): array
    {
        $contractYears = $this->yearsCoveredByContractsForCompany($companyId);
        if ($contractYears === []) {
            return [];
        }

        $now = CarbonImmutable::now();
        $currentYear = $now->year;
        $currentMonth = $now->month;

        $pending = [];
        foreach ($contractYears as $year) {
            $breakdown = $this->breakdown->byCompanyForYear($companyId, $year);

            $count = 0;
            foreach ($breakdown->entries as $entry) {
                // Mois éligibles à génération :
                //   - utilisation effective (daysUsed > 0)
                //   - tarif présent (sinon l'utilisateur doit d'abord
                //     corriger sur la fiche véhicule)
                //   - pas encore facturé (pas de row Invoice active)
                //   - mois écoulé (le mois en cours ou futur ne se
                //     facture pas tant qu'il n'est pas clos)
                if ($entry->daysUsed <= 0) {
                    continue;
                }
                if ($entry->hasMissingPricing) {
                    continue;
                }
                if ($entry->existingInvoiceId !== null) {
                    continue;
                }

                $isPastMonth = $year < $currentYear
                    || ($year === $currentYear && $entry->month < $currentMonth);
                if (! $isPastMonth) {
                    continue;
                }

                $count++;
            }

            if ($count > 0) {
                $pending[] = new PendingInvoiceYearData(
                    fiscalYear: $year,
                    missingInvoicesCount: $count,
                );
            }
        }

        usort(
            $pending,
            static fn (PendingInvoiceYearData $a, PendingInvoiceYearData $b): int => $a->fiscalYear <=> $b->fiscalYear,
        );

        return $pending;
    }

    /**
     * Mêmes années que le `PendingDeclarationsResolver` · plage couverte
     * par les contrats existants de l'entreprise.
     *
     * @return list<int>
     */
    private function yearsCoveredByContractsForCompany(int $companyId): array
    {
        $rows = DB::table('contracts')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->select(['start_date', 'end_date'])
            ->get();

        $years = [];
        foreach ($rows as $row) {
            $start = CarbonImmutable::parse((string) $row->start_date);
            $end = CarbonImmutable::parse((string) $row->end_date);

            for ($y = $start->year; $y <= $end->year; $y++) {
                $years[$y] = true;
            }
        }

        $list = array_keys($years);
        sort($list);

        return $list;
    }
}
