<?php

declare(strict_types=1);

namespace App\Data\User\Company;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Payload of the Fiscality tab for one company × year. Totals are rounded
 * at the aggregate level per R-2024-003.
 */
#[TypeScript]
final class CompanyFiscalYearData extends Data
{
    /**
     * @param  list<CompanyVehicleFiscalRowData>  $rows
     * @param  list<int>  $availableYears
     */
    public function __construct(
        public int $year,
        public int $currentRealYear,
        /** False when no fiscal rules are coded for `$year` · taxes are not computed and the declaration cannot be prepared. */
        public bool $fiscalYearSupported,
        public array $rows,
        public array $availableYears,
        public int $totalDays,
        public float $totalTaxCo2,
        public float $totalTaxPollutants,
        public float $totalTaxAll,
        public int $contractsCount,
    ) {}
}
