<?php

declare(strict_types=1);

namespace App\Services\Invoice;

use App\Contracts\Repositories\User\Contract\ContractReadRepositoryInterface;
use App\Contracts\Repositories\User\Invoice\InvoiceWriteRepositoryInterface;
use Carbon\CarbonImmutable;

/**
 * Application service that flags invoices impacted by a mutation as
 * `is_divergent = true`.
 *
 * Centralises the `(year, month)` enumeration and bulk UPDATE so the
 * three observers (Contract, VehicleYearlyPricing, Vehicle.exit_date)
 * reuse the same logic.
 *
 * ADR-0013 R3 compliant · no direct SQL, every read delegates to
 * {@see ContractReadRepositoryInterface}, every bulk UPDATE to
 * {@see InvoiceWriteRepositoryInterface}. The methods only orchestrate
 * (tuple enumeration, dedup, pivots).
 *
 * Immutability doctrine (ADR-0008) · `is_divergent` is observational
 * metadata, never part of the snapshot. The frozen columns
 * (`total_ht_cents`, `pdf_path`, `pdf_hash`, `invoice_number`,
 * `invoice_lines`) are never mutated here.
 *
 * Cost · one call = one (rarely two or three) conditional bulk UPDATE
 * on `(company_id, year, month)`. No `BillingCalculator` invocation.
 * Pays divergence on write (rare), not on read (every Index).
 */
final readonly class InvoiceDivergenceFlagger
{
    public function __construct(
        private ContractReadRepositoryInterface $contracts,
        private InvoiceWriteRepositoryInterface $invoices,
    ) {}

    /**
     * Flags as divergent the company's invoices whose `(year, month)`
     * falls in the current range or the optional previous range
     * (Update Contract with modified dates).
     *
     * Returns the number of flipped rows.
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
     * Flags as divergent the invoices of the modified pricing's year
     * for every company that had a contract on this vehicle crossing
     * the year. The vehicle → companies pivot and the bulk UPDATE are
     * carried entirely by
     * {@see InvoiceWriteRepositoryInterface::flagDivergentForVehiclePricingYear()}
     * (single round-trip with an embedded subquery).
     */
    public function flagForVehiclePricingYear(int $vehicleId, int $year): int
    {
        return $this->invoices->flagDivergentForVehiclePricingYear($vehicleId, $year);
    }

    /**
     * Flags as divergent the invoices of the company whose
     * `(year, month)` falls within a commercial discount period.
     * Covers creation / mutation / deletion of a `RentalDiscount` ·
     * invoices emitted before the mutation no longer mirror the
     * applicable commercial reality.
     *
     * The invoice snapshot stays frozen (ADR-0008) · only
     * `is_divergent` flips so the user can decide to regenerate.
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
     * Flags as divergent every invoice matching an existing contract
     * of the vehicle (Vehicle.exit_date clipping per ADR-0018). Pivot
     * resolved via `findContractDateRangesForVehicle` then bulk UPDATE
     * per company on enumerated `(year, month)` tuples.
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
     * Enumerates every `(year, month)` covered by the inclusive range
     * `[start, end]`. Returns at least one entry when `start <= end`.
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
     * Dedupes a tuple list by `"{year}-{month}"`.
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
