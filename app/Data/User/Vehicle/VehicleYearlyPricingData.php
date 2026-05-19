<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use App\Models\VehicleYearlyPricing;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * HTTP payload and JSON output for daily/weekly/monthly rental rates of a vehicle
 * on one year. Amounts are stored in cents to avoid float imprecision.
 */
#[TypeScript]
#[MapInputName(SnakeCaseMapper::class)]
final class VehicleYearlyPricingData extends Data
{
    public function __construct(
        #[Required, IntegerType, Min(2020), Max(2099)]
        public int $year,

        #[Required, IntegerType, Min(0), Max(99_999_999)]
        public int $dailyRateCents,

        #[Required, IntegerType, Min(0), Max(99_999_999)]
        public int $weeklyRateCents,

        #[Required, IntegerType, Min(0), Max(99_999_999)]
        public int $monthlyRateCents,
    ) {}

    /**
     * Hydrate from the Eloquent model.
     */
    public static function fromModel(VehicleYearlyPricing $pricing): self
    {
        return new self(
            year: $pricing->year,
            dailyRateCents: $pricing->daily_rate_cents,
            weeklyRateCents: $pricing->weekly_rate_cents,
            monthlyRateCents: $pricing->monthly_rate_cents,
        );
    }
}
