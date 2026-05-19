<?php

declare(strict_types=1);

namespace App\Services\Billing\Discount;

use App\Data\User\Billing\BillingCalculationData;
use App\Data\User\Billing\BillingLineData;
use App\Exceptions\Billing\MissingPricingException;
use App\Models\RentalDiscount;

/**
 * Applies active commercial discounts to a monthly invoice
 * computation. Stateless service following the "strict equivalence
 * branch" doctrine ·
 *
 *   - When the index is empty (99 % of companies without discount),
 *     return the input unchanged · zero mutation, zero allocation,
 *     byte-identical serialisation.
 *   - When a discount covers all `usedDates` of a line, the pro-rata
 *     is 1.
 *   - When it covers only some days, pro-rata =
 *     `discountedDays / daysUsed`.
 *
 * Pro-rata algorithm per line · iterate `usedDates`, ask the resolver
 * `findFor(vehicleId, date)`, count discount days grouped by
 * `RentalDiscount.id`, and compute
 * `discount = round(grossTotalCents * discountedDays / daysUsed *
 * basisPoints / 10000)`.
 *
 * Caveat · the pro-rata averages the cost per day, while the
 * `OptimalRateBreakdown` combo produces variable per-day costs
 * (a day inside a monthly package costs less/day than a day at the
 * unit rate). It is the standard approach for a percentage discount ·
 * simple, predictable, explainable to the customer.
 */
final readonly class DiscountApplier
{
    /**
     * Batch variant · applies to every month of a `calculateYear()`
     * result. Avoids redeclaring the index 12 times.
     *
     * @param  array<int, BillingCalculationData|MissingPricingException>  $monthlyResults
     * @return array<int, BillingCalculationData|MissingPricingException>
     */
    public function applyToMonthlyResults(array $monthlyResults, ResolvedDiscountIndex $index): array
    {
        // Strict equivalence · no discount = byte-identical pass-through.
        if ($index->isEmpty()) {
            return $monthlyResults;
        }

        $applied = [];
        foreach ($monthlyResults as $month => $result) {
            if ($result instanceof MissingPricingException) {
                $applied[$month] = $result;

                continue;
            }
            $applied[$month] = $this->applyWithIndex($result, $index);
        }

        return $applied;
    }

    /**
     * Single-month variant for `GenerateInvoiceAction` or an isolated
     * `BillingCalculator::calculate()` call. The loader preloads the
     * index lazily for the `(companyId, year)` of the calculation.
     *
     * @param  callable(int $companyId, int $year): ResolvedDiscountIndex  $indexLoader
     */
    public function applyToCalculation(
        BillingCalculationData $calc,
        callable $indexLoader,
    ): BillingCalculationData {
        $index = $indexLoader($calc->companyId, $calc->year);

        if ($index->isEmpty()) {
            return $calc;
        }

        return $this->applyWithIndex($calc, $index);
    }

    /**
     * Core of the computation. Rebuilds an immutable
     * `BillingCalculationData` with updated lines (Spatie Data does
     * not allow direct mutation on `final readonly` DTOs).
     */
    public function applyWithIndex(
        BillingCalculationData $calc,
        ResolvedDiscountIndex $index,
    ): BillingCalculationData {
        if ($index->isEmpty()) {
            return $calc;
        }

        $newLines = [];
        $totalNet = 0;
        $totalGross = 0;
        $totalDiscount = 0;

        foreach ($calc->lines as $line) {
            $newLine = $this->applyToLine($line, $index);
            $newLines[] = $newLine;
            $totalNet += $newLine->totalCents;
            $totalGross += $newLine->grossTotalCents;
            $totalDiscount += $newLine->discountCents;
        }

        return new BillingCalculationData(
            companyId: $calc->companyId,
            year: $calc->year,
            month: $calc->month,
            lines: $newLines,
            totalCents: $totalNet,
            grossTotalCents: $totalGross,
            totalDiscountCents: $totalDiscount,
        );
    }

    /**
     * Computes the discount for one line. Edge cases ·
     *   - `daysUsed === 0` or `grossTotalCents === 0` · no discount
     *     possible (empty line).
     *   - `usedDates === []` · pre-discount line or snapshot without
     *     per-day data · return unchanged (no pro-rata possible).
     */
    private function applyToLine(BillingLineData $line, ResolvedDiscountIndex $index): BillingLineData
    {
        if ($line->daysUsed === 0 || $line->grossTotalCents === 0 || $line->usedDates === []) {
            return $line;
        }

        // Group discount days by `RentalDiscount.id` in case multiple
        // discounts match (the ConflictService prevents this in
        // practice; we stay defensive).
        /** @var array<int, array{discount: RentalDiscount, days: int}> $byDiscount */
        $byDiscount = [];

        foreach ($line->usedDates as $date) {
            $discount = $index->findFor($line->vehicleId, $date);
            if ($discount === null) {
                continue;
            }
            $id = (int) $discount->id;
            if (! isset($byDiscount[$id])) {
                $byDiscount[$id] = ['discount' => $discount, 'days' => 0];
            }
            $byDiscount[$id]['days']++;
        }

        if ($byDiscount === []) {
            return $line;
        }

        $totalDiscountCents = 0;
        $appliedDiscount = null;
        $appliedDays = 0;

        foreach ($byDiscount as $entry) {
            /** @var RentalDiscount $discount */
            $discount = $entry['discount'];
            $days = $entry['days'];

            // Pro-rata · `grossTotalCents × (days / daysUsed) ×
            // (bp / 10000)`. Rounded to the nearest cent integer.
            $cents = (int) round(
                $line->grossTotalCents * $days * $discount->discount_basis_points
                    / ($line->daysUsed * 10000),
            );

            $totalDiscountCents += $cents;

            // Capture the "dominant" discount (covers the most days)
            // for the line snapshot. Dominance is unique thanks to
            // the ConflictService.
            if ($appliedDiscount === null || $days > $appliedDays) {
                $appliedDiscount = $discount;
                $appliedDays = $days;
            }
        }

        // Clamp · never deduct more than the gross (defensive against
        // rounding).
        if ($totalDiscountCents > $line->grossTotalCents) {
            $totalDiscountCents = $line->grossTotalCents;
        }

        // `$appliedDiscount` is guaranteed non-null since `$byDiscount`
        // was non-empty.
        /** @var RentalDiscount $appliedDiscount */
        return new BillingLineData(
            vehicleId: $line->vehicleId,
            licensePlate: $line->licensePlate,
            brand: $line->brand,
            model: $line->model,
            daysUsed: $line->daysUsed,
            monthsBilled: $line->monthsBilled,
            weeksBilled: $line->weeksBilled,
            daysBilled: $line->daysBilled,
            dailyRateCents: $line->dailyRateCents,
            weeklyRateCents: $line->weeklyRateCents,
            monthlyRateCents: $line->monthlyRateCents,
            totalCents: $line->grossTotalCents - $totalDiscountCents,
            grossTotalCents: $line->grossTotalCents,
            discountCents: $totalDiscountCents,
            appliedDiscountId: $appliedDiscount->id,
            appliedDiscountBasisPoints: $appliedDiscount->discount_basis_points,
            appliedDiscountLabel: $appliedDiscount->label,
            usedDates: $line->usedDates,
        );
    }
}
