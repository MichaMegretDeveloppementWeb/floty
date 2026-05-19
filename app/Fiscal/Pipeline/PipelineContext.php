<?php

declare(strict_types=1);

namespace App\Fiscal\Pipeline;

use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\PollutantCategory;
use App\Fiscal\ValueObjects\DaysWindow;
use App\Fiscal\ValueObjects\ExemptionVerdict;
use App\Fiscal\Year2024\Transversal\R2024_002_DailyProrata;
use App\Models\Contract;
use App\Models\Unavailability;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;

/**
 * State accumulated while a fiscal calculation runs.
 *
 * Immutable: each rule receives the current context and returns a new
 * instance via `with*()` methods. The pipeline keeps the latest
 * instance and passes it to the next step.
 *
 * Fields typed `?type = null` are computed during the pipeline. A field
 * still `null` at the pricing stage means either no classification was
 * done (degenerate case) or the corresponding rule has not yet run.
 *
 * Per ADR-0014, the pipeline receives the pair's contracts
 * (`contractsForPair`) and the vehicle's unavailabilities for the year
 * (`vehicleUnavailabilitiesInYear`) so sovereign rules (R-2024-021,
 * R-2024-008) can act on raw material. `daysAssignedToCompany` and
 * `cumulativeDaysForPair` are nullable and computed by
 * {@see R2024_002_DailyProrata} from the taxable contracts (after
 * applying daily exemption verdicts).
 */
final readonly class PipelineContext
{
    /**
     * @param  list<Contract>  $contractsForPair  Active contracts of the pair over the year
     * @param  list<Unavailability>  $vehicleUnavailabilitiesInYear  Vehicle unavailabilities over the year
     * @param  list<ExemptionVerdict>  $exemptionVerdicts  Verdicts collected at step 4
     * @param  list<string>  $appliedRuleCodes  Trace for the PDF snapshot
     */
    public function __construct(
        public Vehicle $vehicle,
        public int $fiscalYear,
        public int $daysInYear,
        public array $contractsForPair = [],
        public array $vehicleUnavailabilitiesInYear = [],
        public ?int $daysAssignedToCompany = null,
        public ?int $cumulativeDaysForPair = null,
        public ?VehicleFiscalCharacteristics $currentFiscalCharacteristics = null,
        public ?bool $isFiscallyTaxable = null,
        public ?string $isFiscallyTaxableReason = null,
        public ?HomologationMethod $resolvedCo2Method = null,
        public ?PollutantCategory $resolvedPollutantCategory = null,
        public ?float $co2FullYearTariff = null,
        public ?float $pollutantsFullYearTariff = null,
        public ?float $co2Due = null,
        public ?float $pollutantsDue = null,
        public array $exemptionVerdicts = [],
        public array $appliedRuleCodes = [],
        /**
         * Day window set by {@see FiscalSegmentedExecutor} when a
         * calculation is segmented by VFC. Restricts the day count in
         * R-2024-002 without clipping contract definitions (R-2024-021
         * LCD judges on the contract's full duration). `null` means no
         * restriction (single-VFC mode).
         */
        public ?DaysWindow $daysWindow = null,
    ) {}

    public function withCurrentFiscalCharacteristics(VehicleFiscalCharacteristics $vfc): self
    {
        return $this->copyWith(['currentFiscalCharacteristics' => $vfc]);
    }

    public function withIsFiscallyTaxable(bool $taxable): self
    {
        return $this->copyWith(['isFiscallyTaxable' => $taxable]);
    }

    public function withFiscallyTaxableReason(?string $reason): self
    {
        return $this->copyWith(['isFiscallyTaxableReason' => $reason]);
    }

    public function withResolvedCo2Method(HomologationMethod $method): self
    {
        return $this->copyWith(['resolvedCo2Method' => $method]);
    }

    public function withResolvedPollutantCategory(PollutantCategory $category): self
    {
        return $this->copyWith(['resolvedPollutantCategory' => $category]);
    }

    public function withCo2FullYearTariff(float $tariff): self
    {
        return $this->copyWith(['co2FullYearTariff' => $tariff]);
    }

    public function withPollutantsFullYearTariff(float $tariff): self
    {
        return $this->copyWith(['pollutantsFullYearTariff' => $tariff]);
    }

    public function withDueAmounts(float $co2Due, float $pollutantsDue): self
    {
        return $this->copyWith(['co2Due' => $co2Due, 'pollutantsDue' => $pollutantsDue]);
    }

    public function withDaysAssignedToCompany(int $days): self
    {
        return $this->copyWith(['daysAssignedToCompany' => $days]);
    }

    public function withCumulativeDaysForPair(int $days): self
    {
        return $this->copyWith(['cumulativeDaysForPair' => $days]);
    }

    public function withExemptionVerdict(ExemptionVerdict $verdict): self
    {
        return $this->copyWith(['exemptionVerdicts' => [...$this->exemptionVerdicts, $verdict]]);
    }

    public function withAppliedRule(string $ruleCode): self
    {
        return $this->copyWith(['appliedRuleCodes' => [...$this->appliedRuleCodes, $ruleCode]]);
    }

    public function withDaysWindow(?DaysWindow $window): self
    {
        return $this->copyWith(['daysWindow' => $window]);
    }

    /**
     * Clones the instance replacing the supplied fields. Adding a new
     * field to the constructor needs no change here.
     *
     * Uses `array_key_exists` (not `??`) so intentional falsy overrides
     * (`false`, `0`, `null`) are honoured.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function copyWith(array $overrides): self
    {
        $pick = fn (string $key, mixed $current): mixed => array_key_exists($key, $overrides)
            ? $overrides[$key]
            : $current;

        return new self(
            vehicle: $pick('vehicle', $this->vehicle),
            fiscalYear: $pick('fiscalYear', $this->fiscalYear),
            daysInYear: $pick('daysInYear', $this->daysInYear),
            contractsForPair: $pick('contractsForPair', $this->contractsForPair),
            vehicleUnavailabilitiesInYear: $pick('vehicleUnavailabilitiesInYear', $this->vehicleUnavailabilitiesInYear),
            daysAssignedToCompany: $pick('daysAssignedToCompany', $this->daysAssignedToCompany),
            cumulativeDaysForPair: $pick('cumulativeDaysForPair', $this->cumulativeDaysForPair),
            currentFiscalCharacteristics: $pick('currentFiscalCharacteristics', $this->currentFiscalCharacteristics),
            isFiscallyTaxable: $pick('isFiscallyTaxable', $this->isFiscallyTaxable),
            isFiscallyTaxableReason: $pick('isFiscallyTaxableReason', $this->isFiscallyTaxableReason),
            resolvedCo2Method: $pick('resolvedCo2Method', $this->resolvedCo2Method),
            resolvedPollutantCategory: $pick('resolvedPollutantCategory', $this->resolvedPollutantCategory),
            co2FullYearTariff: $pick('co2FullYearTariff', $this->co2FullYearTariff),
            pollutantsFullYearTariff: $pick('pollutantsFullYearTariff', $this->pollutantsFullYearTariff),
            co2Due: $pick('co2Due', $this->co2Due),
            pollutantsDue: $pick('pollutantsDue', $this->pollutantsDue),
            exemptionVerdicts: $pick('exemptionVerdicts', $this->exemptionVerdicts),
            appliedRuleCodes: $pick('appliedRuleCodes', $this->appliedRuleCodes),
            daysWindow: $pick('daysWindow', $this->daysWindow),
        );
    }
}
