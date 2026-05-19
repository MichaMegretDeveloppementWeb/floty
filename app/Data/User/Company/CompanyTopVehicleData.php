<?php

declare(strict_types=1);

namespace App\Data\User\Company;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row of the top vehicles assigned to a company on a fiscal year.
 * `percentage` is computed against the company's total vehicle-days for the year.
 */
#[TypeScript]
final class CompanyTopVehicleData extends Data
{
    public function __construct(
        public int $vehicleId,
        public string $licensePlate,
        public string $brand,
        public string $model,
        public int $daysUsed,
        /** Percentage [0..100] rounded to 1 decimal of the company's annual vehicle-days. */
        public float $percentage,
    ) {}
}
