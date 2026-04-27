<?php

declare(strict_types=1);

namespace App\Fiscal\Year2024\Exemption;

use App\Enums\Fiscal\TaxType;
use App\Enums\Vehicle\EnergySource;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\UnderlyingCombustionEngineType;
use App\Fiscal\Contracts\ExemptionRule;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\ValueObjects\ExemptionVerdict;
use App\Models\VehicleFiscalCharacteristics;
use Illuminate\Support\Carbon;

/**
 * R-2024-017 — Exonération hybride conditionnelle 2024 (CIBS L. 421-125).
 *
 * **Applicable uniquement en 2024** (supprimée par la LF 2025).
 * Concerne la **taxe CO₂ uniquement** (la taxe polluants reste due).
 *
 * Conditions cumulatives :
 *   1. Combinaison de sources d'énergie éligible
 *      - (a) électricité ou hydrogène + essence / GPL / GNV / E85
 *      - (b) GNV / GPL + essence / E85 (combinaison non modélisée
 *        par les enums Floty actuels — ignorée en V1)
 *   2. Seuils d'émissions/puissance selon la méthode CO₂ et
 *      l'ancienneté du véhicule au 01/01/2024 :
 *      - régime général (≥ 3 ans) : WLTP ≤ 60, NEDC ≤ 50, PA ≤ 3 CV
 *      - régime aménagé (< 3 ans) : WLTP ≤ 120, NEDC ≤ 100, PA ≤ 6 CV
 *
 * Note V1 : la combinaison (b) GNV/GPL + essence n'est pas modélisable
 * avec l'enum {@see EnergySource} actuel (qui n'a qu'une source
 * primaire + un sous-jacent thermique). À la pratique, les véhicules
 * concernés sont rarissimes côté flotte Floty. À étendre si besoin
 * client futur.
 */
final readonly class R2024_017_ConditionalHybridExemption implements ExemptionRule
{
    private const int THRESHOLD_WLTP_GENERAL = 60;

    private const int THRESHOLD_WLTP_ADJUSTED = 120;

    private const int THRESHOLD_NEDC_GENERAL = 50;

    private const int THRESHOLD_NEDC_ADJUSTED = 100;

    private const int THRESHOLD_PA_GENERAL = 3;

    private const int THRESHOLD_PA_ADJUSTED = 6;

    private const int AGE_THRESHOLD_YEARS = 3;

    public function ruleCode(): string
    {
        return 'R-2024-017';
    }

    /**
     * @return list<TaxType>
     */
    public function taxesConcerned(): array
    {
        return [TaxType::Co2];
    }

    public function evaluate(PipelineContext $context): ExemptionVerdict
    {
        $fiscal = $context->currentFiscalCharacteristics;
        if ($fiscal === null) {
            return ExemptionVerdict::notExempt();
        }

        if (! $this->hasEligibleCombination($fiscal)) {
            return ExemptionVerdict::notExempt();
        }

        $referenceDate = Carbon::create($context->fiscalYear, 1, 1);
        $vehicleAgeYears = $context->vehicle->first_origin_registration_date->diffInYears($referenceDate);
        $isAdjustedRegime = $vehicleAgeYears < self::AGE_THRESHOLD_YEARS;

        if (! $this->meetsThresholds($fiscal, $isAdjustedRegime)) {
            return ExemptionVerdict::notExempt();
        }

        return ExemptionVerdict::onlyCo2(
            'Exonération hybride conditionnelle 2024 (CIBS L. 421-125)',
        );
    }

    /**
     * Combinaison (a) : hybride à sous-jacent essence (le cas modélisé
     * par EnergySource + UnderlyingCombustionEngineType). La
     * combinaison (b) GNV/GPL + essence n'est pas modélisable en V1.
     */
    private function hasEligibleCombination(VehicleFiscalCharacteristics $fiscal): bool
    {
        $isHybrid = in_array(
            $fiscal->energy_source,
            [EnergySource::PluginHybrid, EnergySource::NonPluginHybrid],
            true,
        );

        return $isHybrid
            && $fiscal->underlying_combustion_engine_type === UnderlyingCombustionEngineType::Gasoline;
    }

    private function meetsThresholds(VehicleFiscalCharacteristics $fiscal, bool $isAdjustedRegime): bool
    {
        return match ($fiscal->homologation_method) {
            HomologationMethod::Wltp => $fiscal->co2_wltp !== null
                && $fiscal->co2_wltp <= ($isAdjustedRegime
                    ? self::THRESHOLD_WLTP_ADJUSTED
                    : self::THRESHOLD_WLTP_GENERAL),
            HomologationMethod::Nedc => $fiscal->co2_nedc !== null
                && $fiscal->co2_nedc <= ($isAdjustedRegime
                    ? self::THRESHOLD_NEDC_ADJUSTED
                    : self::THRESHOLD_NEDC_GENERAL),
            HomologationMethod::Pa => $fiscal->taxable_horsepower !== null
                && $fiscal->taxable_horsepower <= ($isAdjustedRegime
                    ? self::THRESHOLD_PA_ADJUSTED
                    : self::THRESHOLD_PA_GENERAL),
        };
    }
}
