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
 * Pendant Spatie Data, exposé front, du DTO interne
 * {@see FiscalBreakdown}.
 *
 * Conversion explicite via {@see self::fromBreakdown()} - pas de
 * conversion magique par réflexion.
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
     * Construit le DTO exposé à partir du DTO interne. Garantit le
     * mapping 1:1 typé entre les deux représentations.
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
