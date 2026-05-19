<?php

declare(strict_types=1);

namespace App\Data\User\Fiscal;

use App\DTO\Fiscal\FiscalBreakdown;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\PollutantCategory;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Frontend-facing Spatie Data counterpart of the internal
 * {@see FiscalBreakdown} DTO. Conversion is explicit through
 * {@see self::fromBreakdown()}; no reflection magic.
 */
#[TypeScript]
final class FiscalBreakdownData extends Data
{
    /**
     * @param  list<AppliedExemptionData>  $appliedExemptions
     */
    public function __construct(
        public int $daysAssigned,
        public int $cumulativeDaysForPair,
        public int $daysInYear,
        public bool $lcdExempt,
        public bool $electricExempt,
        public bool $handicapExempt,
        public HomologationMethod $co2Method,
        public float $co2FullYearTariff,
        public float $co2Due,
        public PollutantCategory $pollutantCategory,
        public float $pollutantsFullYearTariff,
        public float $pollutantsDue,
        public float $totalDue,
        #[DataCollectionOf(AppliedExemptionData::class)]
        public array $appliedExemptions,
    ) {}

    /**
     * Build from the internal breakdown, guaranteeing a 1:1 typed mapping.
     */
    public static function fromBreakdown(FiscalBreakdown $breakdown): self
    {
        return new self(
            daysAssigned: $breakdown->daysAssigned,
            cumulativeDaysForPair: $breakdown->cumulativeDaysForPair,
            daysInYear: $breakdown->daysInYear,
            lcdExempt: $breakdown->lcdExempt,
            electricExempt: $breakdown->electricExempt,
            handicapExempt: $breakdown->handicapExempt,
            co2Method: $breakdown->co2Method,
            co2FullYearTariff: $breakdown->co2FullYearTariff,
            co2Due: $breakdown->co2Due,
            pollutantCategory: $breakdown->pollutantCategory,
            pollutantsFullYearTariff: $breakdown->pollutantsFullYearTariff,
            pollutantsDue: $breakdown->pollutantsDue,
            totalDue: $breakdown->totalDue,
            appliedExemptions: array_map(
                static fn ($e) => AppliedExemptionData::fromValueObject($e),
                $breakdown->appliedExemptions,
            ),
        );
    }
}
