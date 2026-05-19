<?php

declare(strict_types=1);

namespace App\Data\User\Company;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Row of the per-vehicle fiscal breakdown table on the Company Fiscality tab.
 * One row per (vehicle × company × year) triple.
 */
#[TypeScript]
final class CompanyVehicleFiscalRowData extends Data
{
    public function __construct(
        public int $vehicleId,
        public string $licensePlate,
        public ?string $brand,
        public ?string $model,
        public int $daysUsed,
        public float $proratoPercent,
        public float $taxCo2,
        public float $taxPollutants,
        public float $taxTotal,
    ) {}
}
