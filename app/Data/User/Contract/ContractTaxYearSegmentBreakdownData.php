<?php

declare(strict_types=1);

namespace App\Data\User\Contract;

use App\Data\User\Fiscal\AppliedExemptionData;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\PollutantCategory;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Tariff breakdown for a single window (VFC × Rules segment) inside a
 * contract's yearly fiscal calculation.
 *
 * A window emerges when a rule or VFC change splits `contract × year` into
 * sub-periods tariffed differently. Examples:
 *   - Pollutants 2026 split at 01/03 (LF 2026 art. 58 V IV, +30%): a
 *     15/01-24/04 contract has two windows (01/01-28/02 R-2026-014
 *     Cat1=100€, 01/03-31/12 R-2026-014-bis Cat1=130€), clipped to 45 + 55
 *     days.
 *   - Multi-VFC: a vehicle switching from Diesel to Hybrid mid-year
 *     produces two windows with distinct pollutant tariffs.
 *
 * When no split applies, the list holds a single segment covering the
 * whole `contract × year` period.
 *
 * Fields:
 *   - `effectiveFromInYear` / `effectiveToInYear`: inclusive window
 *     bounds after contract × VFC × Rules intersection.
 *   - `daysAssignedToContract`: contract days inside the window, after
 *     daily exemptions; numerator of `tariff × daysAssigned / daysInYear`.
 *   - `co2FullYearTariff` / `pollutantsFullYearTariff`: annual tariff
 *     applicable to this window (may differ across windows).
 *   - `co2Due` / `pollutantsDue`: due amount for this window only
 *     (tariff × daysAssigned / daysInYear, rounded half-up).
 *   - `appliedRuleCodes` / `appliedExemptions`: rules and exemptions
 *     active specifically on this window.
 */
#[TypeScript]
final class ContractTaxYearSegmentBreakdownData extends Data
{
    /**
     * @param  list<AppliedExemptionData>  $appliedExemptions
     * @param  list<string>  $appliedRuleCodes
     */
    public function __construct(
        public string $effectiveFromInYear,
        public string $effectiveToInYear,
        public int $daysAssignedToContract,
        public int $daysInYear,
        public HomologationMethod $co2Method,
        public PollutantCategory $pollutantCategory,
        public float $co2FullYearTariff,
        public float $pollutantsFullYearTariff,
        public float $co2Due,
        public float $pollutantsDue,
        #[DataCollectionOf(AppliedExemptionData::class)]
        public array $appliedExemptions,
        public array $appliedRuleCodes,
    ) {}
}
