<?php

declare(strict_types=1);

namespace App\Data\User\Billing;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One cell of the contract billing recap, for a single civil month.
 *
 * When the yearly tariff is not defined on the vehicle for the relevant
 * year, `totalCents` is `null` and `hasMissingPricing = true`.
 */
#[TypeScript]
final class ContractBillingMonthData extends Data
{
    public function __construct(
        public int $year,
        public int $month,
        /** Number of contract days falling in this civil month (intersection). */
        public int $daysInMonth,
        public ?int $totalCents,
        public bool $hasMissingPricing,
        /**
         * GROSS month total (= before commercial discount). `null` when
         * tariff missing. Equal to `totalCents` when no discount applies.
         */
        public ?int $grossTotalCents = null,
        /**
         * Commercial discount applied to the month (cents). `null` when
         * tariff missing, `0` when no discount applies.
         */
        public ?int $discountCents = null,
    ) {}
}
