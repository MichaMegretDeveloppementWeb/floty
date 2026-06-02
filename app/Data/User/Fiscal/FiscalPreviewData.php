<?php

declare(strict_types=1);

namespace App\Data\User\Fiscal;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Response of `POST /app/planning/preview-taxes`: standalone fiscal cost
 * of a single contract.
 *
 * LCD vs LLD is qualified per contract using its own duration only
 * (<= 30 days -> LCD, otherwise LLD). There is no annual cumulation
 * across a `(vehicle, company)` couple at this stage.
 */
#[TypeScript]
final class FiscalPreviewData extends Data
{
    public function __construct(
        public int $fiscalYear,
        /** Days kept in the year for this contract. */
        public int $daysCount,
        /** CO₂ + pollutants + total + applied exemptions. Null when the year has no coded fiscal rules. */
        public ?FiscalBreakdownData $breakdown,
        /** False when the year has no coded fiscal rules · `breakdown` is null and no tax is computed. */
        public bool $supported = true,
    ) {}
}
