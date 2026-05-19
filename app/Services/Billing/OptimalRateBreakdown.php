<?php

declare(strict_types=1);

namespace App\Services\Billing;

/**
 * Cheapest combination of monthly/weekly/daily units covering N days.
 *
 * Coverage convention · 1 month = 30 days, 1 week = 7 days, 1 day = 1.
 * The civil month split is applied upstream by {@see BillingCalculator}.
 * The search space is bounded (≤ 12 candidates), so the algorithm is
 * deterministic O(1). Always optimises for the client (cheapest total);
 * no minimum-billed-week policy.
 */
final readonly class OptimalRateBreakdown
{
    private const int DAYS_PER_MONTH_UNIT = 30;

    private const int DAYS_PER_WEEK_UNIT = 7;

    /**
     * @param  int  $months  Billed month-units.
     * @param  int  $weeks  Billed week-units.
     * @param  int  $days  Residual day-units.
     * @param  int  $totalCents  `months × monthly + weeks × weekly + days × daily`.
     */
    public function __construct(
        public int $months,
        public int $weeks,
        public int $days,
        public int $totalCents,
    ) {}

    /**
     * Builds the optimal breakdown for `daysUsed` against the three rates
     * (cents, ≥ 0). Returns a zeroed breakdown when `daysUsed === 0`.
     */
    public static function compute(
        int $daysUsed,
        int $dailyCents,
        int $weeklyCents,
        int $monthlyCents,
    ): self {
        if ($daysUsed === 0) {
            return new self(0, 0, 0, 0);
        }

        $bestCost = PHP_INT_MAX;
        $bestMonths = 0;
        $bestWeeks = 0;
        $bestDays = 0;

        // m ∈ {0, 1}. BillingCalculator already splits ranges > 30 days
        // by civil month, so daysUsed ≤ 31 and m=2 is irrelevant.
        for ($m = 0; $m <= 1; $m++) {
            $remainAfterMonth = max(0, $daysUsed - $m * self::DAYS_PER_MONTH_UNIT);

            // w ∈ {0..5} covers every useful case (5×7 = 35 ≥ 31).
            for ($w = 0; $w <= 5; $w++) {
                $remainAfterWeek = max(0, $remainAfterMonth - $w * self::DAYS_PER_WEEK_UNIT);
                $d = $remainAfterWeek;

                $cost = $m * $monthlyCents + $w * $weeklyCents + $d * $dailyCents;

                if ($cost < $bestCost) {
                    $bestCost = $cost;
                    $bestMonths = $m;
                    $bestWeeks = $w;
                    $bestDays = $d;
                }
            }
        }

        return new self($bestMonths, $bestWeeks, $bestDays, $bestCost);
    }
}
