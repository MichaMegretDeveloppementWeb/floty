<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use App\Enums\Company\CompanyColor;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Per-company row of the vehicle fiscal usage breakdown for one fiscal year.
 */
#[TypeScript]
final class VehicleCompanyUsageData extends Data
{
    public function __construct(
        public int $companyId,
        public string $shortCode,
        public string $legalName,
        public CompanyColor $color,
        public int $daysUsed,
        public float $proratoPercent,
        public float $taxCo2,
        public float $taxPollutants,
        public float $taxTotal,
    ) {}
}
