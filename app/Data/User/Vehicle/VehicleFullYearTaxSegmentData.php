<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use App\Data\User\Fiscal\AppliedExemptionData;
use App\Enums\Fiscal\SegmentBoundaryCause;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\PollutantCategory;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Tariff breakdown of one segment inside the full-year tax calculation
 * of a vehicle. One segment when both the VFC and the rule set cover the
 * whole year, N segments when either the VFC changes or the rule set
 * evolves mid-year (each segment prorated on its day count).
 *
 * `boundaryCause` exposes how this segment came to be (first segment of
 * the year, new VFC, new rule window, or both) so the UI can label the
 * cut clearly instead of misleadingly attributing every cut to a VFC
 * change.
 */
#[TypeScript]
final class VehicleFullYearTaxSegmentData extends Data
{
    /**
     * @param  list<AppliedExemptionData>  $appliedExemptions
     * @param  list<string>  $appliedRuleCodes
     */
    public function __construct(
        public string $effectiveFromInYear,
        public string $effectiveToInYear,
        public int $daysInSegment,
        public SegmentBoundaryCause $boundaryCause,
        public VehicleFiscalCharacteristicsData $vfc,
        public HomologationMethod $co2Method,
        public float $co2FullYearTariff,
        public string $co2Explanation,
        public float $co2Due,
        public PollutantCategory $pollutantCategory,
        public float $pollutantsFullYearTariff,
        public string $pollutantsExplanation,
        public float $pollutantsDue,
        #[DataCollectionOf(AppliedExemptionData::class)]
        public array $appliedExemptions,
        public array $appliedRuleCodes,
    ) {}
}
