<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use App\Data\User\Fiscal\AppliedExemptionData;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\PollutantCategory;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Tariff breakdown of one VFC segment inside the full-year tax calculation of a vehicle.
 * One segment when the vehicle has a single VFC across the year, N segments when the VFC
 * changes mid-year (each segment prorated on its day count).
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
