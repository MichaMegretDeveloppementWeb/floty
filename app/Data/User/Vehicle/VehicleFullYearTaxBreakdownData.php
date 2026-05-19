<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use App\Data\User\Fiscal\AppliedExemptionData;
use App\Data\User\Fiscal\FiscalRuleListItemData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Full-year tax breakdown of a vehicle, split per active VFC segment.
 * Class-level totals round per R-2024-003; per-segment fields preserve the
 * detail required for multi-VFC explanation.
 */
#[TypeScript]
final class VehicleFullYearTaxBreakdownData extends Data
{
    /**
     * @param  list<AppliedExemptionData>  $appliedExemptions
     * @param  list<string>  $appliedRuleCodes
     * @param  list<FiscalRuleListItemData>  $appliedRules
     * @param  list<VehicleFullYearTaxSegmentData>  $taxSegments
     */
    public function __construct(
        public int $daysInYear,
        public float $total,
        #[DataCollectionOf(AppliedExemptionData::class)]
        public array $appliedExemptions,
        public array $appliedRuleCodes,
        #[DataCollectionOf(FiscalRuleListItemData::class)]
        public array $appliedRules,
        #[DataCollectionOf(VehicleFullYearTaxSegmentData::class)]
        public array $taxSegments,
    ) {}
}
