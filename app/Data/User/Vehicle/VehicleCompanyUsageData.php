<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use App\Enums\Company\CompanyColor;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Per-company row of the vehicle usage breakdown for one fiscal year.
 * `daysUsed` / `proratoPercent` are operational (days actually rented); the tax
 * columns are the fiscal amounts (0 when those days are exonerated, e.g. LCD).
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
