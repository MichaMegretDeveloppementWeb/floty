<?php

declare(strict_types=1);

namespace App\Data\User\Contract;

use App\Data\User\Fiscal\AppliedExemptionData;
use App\Data\User\Fiscal\FiscalRuleListItemData;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\PollutantCategory;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Fiscal breakdown of a contract for a single civil year it crosses.
 *
 * A contract may span two civil years (e.g. 1 Nov 2024 to 31 Jan 2025).
 * The fiscal engine runs per year and emits one instance per year; the
 * parent {@see ContractTaxBreakdownData} carries the list and cross-year
 * total.
 *
 * - `daysInContractInYear`: contract days falling in the year before
 *   exemption (helps explain "X days in the year").
 * - `daysAssigned`: prorata numerator after daily exemption rules
 *   (R-2024-021 LCD, R-2024-008 reducing unavailabilities).
 * - `daysInYear`: denominator (366 in leap year 2024).
 * - `appliedRules`: full detail (name, description, legal refs) for the
 *   codes listed in `appliedRuleCodes`, letting the UI surface a rule
 *   sheet without a server round-trip.
 */
#[TypeScript]
final class ContractTaxYearBreakdownData extends Data
{
    /**
     * @param  list<AppliedExemptionData>  $appliedExemptions
     * @param  list<string>  $appliedRuleCodes
     * @param  list<FiscalRuleListItemData>  $appliedRules
     * @param  list<ContractTaxYearSegmentBreakdownData>  $segments
     */
    public function __construct(
        public int $year,
        public int $daysInContractInYear,
        public int $daysAssigned,
        public int $daysInYear,
        public HomologationMethod $co2Method,
        public PollutantCategory $pollutantCategory,
        public float $co2FullYearTariff,
        public float $pollutantsFullYearTariff,
        public float $co2Due,
        public float $pollutantsDue,
        public float $totalDue,
        #[DataCollectionOf(AppliedExemptionData::class)]
        public array $appliedExemptions,
        public array $appliedRuleCodes,
        #[DataCollectionOf(FiscalRuleListItemData::class)]
        public array $appliedRules,
        /**
         * Per-window detail (VFC × Rules). Always contains at least one
         * element; more than one means the year is split (mid-year tariff
         * change or multi-VFC), letting the UI render the explicit formula
         * per window instead of a misleading aggregated line.
         *
         * @var list<ContractTaxYearSegmentBreakdownData>
         */
        #[DataCollectionOf(ContractTaxYearSegmentBreakdownData::class)]
        public array $segments = [],
        /**
         * Hypothetical CO2 amount "if not LCD"; populated only when
         * R-2024-021 is actually applied (null otherwise to avoid
         * redundant noise). Drives the cluster reclassification UI hint.
         */
        public ?float $hypotheticalCo2DueIfNoLcd = null,
        /** Hypothetical pollutants amount "if not LCD". */
        public ?float $hypotheticalPollutantsDueIfNoLcd = null,
        /** Hypothetical total = co2 + pollutants. */
        public ?float $hypotheticalTotalDueIfNoLcd = null,
    ) {}
}
