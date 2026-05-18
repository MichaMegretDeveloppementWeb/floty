<?php

declare(strict_types=1);

namespace App\Services\Invoice;

use App\Contracts\Repositories\User\Contract\ContractReadRepositoryInterface;
use App\Contracts\Repositories\User\Invoice\InvoiceWriteRepositoryInterface;
use Carbon\CarbonImmutable;

/**
 * Service applicatif chargé de marquer les factures impactées par une
 * mutation comme `is_divergent = true` (T6 / Phase 14.R V1.2).
 *
 * **Pourquoi un service dédié** · centraliser la logique d'énumération
 * `(year, month)` et de bulk UPDATE pour qu'elle soit réutilisable par
 * les 3 observers (Contract, VehicleYearlyPricing, Vehicle.exit_date)
 * sans duplication.
 *
 * **Conformité ADR-0013 R3** (Lot 4 D01 · F-34-001 + F-34-202 · plan-remediation
 * Vague 1) · le service ne fait plus aucun SQL direct. Toutes les
 * lectures sont déléguées à {@see ContractReadRepositoryInterface}, et
 * tous les bulk UPDATE à {@see InvoiceWriteRepositoryInterface}. Les
 * 3 méthodes du service ne portent plus que la logique d'orchestration
 * (énumération de tuples `(year, month)`, déduplication, pivots).
 *
 * **Doctrine immuabilité** (ADR-0008) · `is_divergent` est une
 * métadonnée d'observabilité, pas un champ du snapshot. Les colonnes
 * figées (`total_ht_cents`, `pdf_path`, `pdf_hash`, `invoice_number`,
 * `invoice_lines`) ne sont jamais mutées par ce service.
 *
 * **Coût** · un appel = un (rarement deux ou trois) bulk UPDATE
 * conditionnel sur `(company_id, year, month)`. Aucun appel à
 * `BillingCalculator`. L'intérêt principal · faire payer la divergence
 * à l'écriture (rare) plutôt qu'à la lecture (à chaque Index).
 */
final readonly class InvoiceDivergenceFlagger
{
    public function __construct(
        private ContractReadRepositoryInterface $contracts,
        private InvoiceWriteRepositoryInterface $invoices,
    ) {}

    /**
     * Marque divergentes les factures de l'entreprise dont (year, month)
     * tombe dans le range courant ou l'éventuel range précédent (cas
     * Update Contract avec dates modifiées).
     *
     * Retourne le nombre de lignes flippées (utile pour les tests · en
     * pratique on n'agit pas sur le retour).
     */
    public function flagForContractRange(
        int $companyId,
        string $rangeStart,
        string $rangeEnd,
        ?string $previousRangeStart = null,
        ?string $previousRangeEnd = null,
    ): int {
        $tuples = $this->expandRange($rangeStart, $rangeEnd);
        if ($previousRangeStart !== null && $previousRangeEnd !== null) {
            $tuples = $this->deduplicate(array_merge(
                $tuples,
                $this->expandRange($previousRangeStart, $previousRangeEnd),
            ));
        }

        return $this->invoices->flagDivergentForCompanyAndTuples($companyId, $tuples);
    }

    /**
     * Marque divergentes les factures de l'année du tarif modifié, pour
     * toutes les entreprises ayant eu un contrat sur ce véhicule
     * chevauchant l'année.
     *
     * Le pivot vehicle → companies + l'UPDATE bulk sont entièrement
     * portés par {@see InvoiceWriteRepositoryInterface::flagDivergentForVehiclePricingYear}
     * (1 round-trip BDD avec subquery embedded).
     */
    public function flagForVehiclePricingYear(int $vehicleId, int $year): int
    {
        return $this->invoices->flagDivergentForVehiclePricingYear($vehicleId, $year);
    }

    /**
     * Marque divergentes les factures de l'entreprise dont (year, month)
     * tombe dans la période d'une réduction commerciale (Lot 3 ·
     * propagation UI). Couvre la création / mutation / suppression d'une
     * `RentalDiscount` · les factures émises avant la mutation ne reflètent
     * plus la réalité commerciale en vigueur.
     *
     * Le snapshot facture reste figé (doctrine ADR-0008) · seule la flag
     * `is_divergent` est mise à `true` pour permettre à l'utilisateur de
     * décider d'une régénération.
     */
    public function flagForDiscountPeriod(
        int $companyId,
        string $startDate,
        string $endDate,
        ?string $previousStartDate = null,
        ?string $previousEndDate = null,
    ): int {
        $tuples = $this->expandRange($startDate, $endDate);
        if ($previousStartDate !== null && $previousEndDate !== null) {
            $tuples = $this->deduplicate(array_merge(
                $tuples,
                $this->expandRange($previousStartDate, $previousEndDate),
            ));
        }

        return $this->invoices->flagDivergentForCompanyAndTuples($companyId, $tuples);
    }

    /**
     * Marque divergentes toutes les factures correspondant à un contrat
     * existant du véhicule (cas Vehicle.exit_date qui clip la facturation,
     * cf. T5 / ADR-0018).
     *
     * Pivot effectué via `findContractDateRangesForVehicle` puis bulk
     * UPDATE par company sur les tuples `(year, month)` énumérés.
     */
    public function flagForVehicle(int $vehicleId): int
    {
        $contracts = $this->contracts->findContractDateRangesForVehicle($vehicleId);

        /** @var array<int, list<array{year:int,month:int}>> $byCompany */
        $byCompany = [];
        foreach ($contracts as $contract) {
            $companyId = (int) $contract->company_id;
            foreach ($this->expandRange((string) $contract->start_date, (string) $contract->end_date) as $tuple) {
                $byCompany[$companyId][] = $tuple;
            }
        }

        $total = 0;
        foreach ($byCompany as $companyId => $tuples) {
            $total += $this->invoices->flagDivergentForCompanyAndTuples($companyId, $this->deduplicate($tuples));
        }

        return $total;
    }

    /**
     * Énumère tous les couples (year, month) couverts par le range
     * inclusif `[start, end]`. Retourne au moins une entrée si
     * `start <= end`.
     *
     * @return list<array{year:int,month:int}>
     */
    private function expandRange(string $start, string $end): array
    {
        $cursor = CarbonImmutable::parse($start)->startOfMonth();
        $endCarbon = CarbonImmutable::parse($end);

        if ($cursor->isAfter($endCarbon)) {
            return [];
        }

        $tuples = [];
        while ($cursor->lessThanOrEqualTo($endCarbon)) {
            $tuples[] = ['year' => $cursor->year, 'month' => $cursor->month];
            $cursor = $cursor->addMonth();
        }

        return $tuples;
    }

    /**
     * Déduplique une liste de tuples sur la clé `"{year}-{month}"`.
     *
     * @param  list<array{year:int,month:int}>  $tuples
     * @return list<array{year:int,month:int}>
     */
    private function deduplicate(array $tuples): array
    {
        $seen = [];
        $deduped = [];
        foreach ($tuples as $tuple) {
            $key = "{$tuple['year']}-{$tuple['month']}";
            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $deduped[] = $tuple;
            }
        }

        return $deduped;
    }
}
