<?php

declare(strict_types=1);

namespace App\Fiscal\Year2024\Exemption;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Enums\Vehicle\EnergySource;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\UnderlyingCombustionEngineType;
use App\Fiscal\Contracts\Concerns\AnnualRuleTrait;
use App\Fiscal\Contracts\Concerns\RuleActiveByDefaultTrait;
use App\Fiscal\Contracts\ExemptionRule;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\ValueObjects\ExemptionVerdict;
use App\Fiscal\ValueObjects\RulePedagogicalContent;
use App\Models\VehicleFiscalCharacteristics;
use Illuminate\Support\Carbon;

/**
 * R-2024-017 · conditional hybrid exemption 2024 (CIBS L. 421-125).
 *
 * Applicable only in 2024 (removed by LF 2025). Concerns the CO₂ tax
 * only (the pollutants tax remains due).
 *
 * Cumulative conditions:
 *   1. Eligible energy-source combination:
 *      - (a) electricity or hydrogen + petrol / LPG / CNG / E85
 *      - (b) CNG / LPG + petrol / E85 (combination not modelled by
 *        current Floty enums · ignored in V1)
 *   2. Emission/power thresholds depending on CO₂ method and vehicle
 *      age at 01/01/2024:
 *      - general regime (≥ 3 years): WLTP ≤ 60, NEDC ≤ 50, PA ≤ 3 CV
 *      - adjusted regime (< 3 years): WLTP ≤ 120, NEDC ≤ 100, PA ≤ 6 CV
 *
 * V1 limit: combination (b) CNG/LPG + petrol is not modellable with
 * the current {@see EnergySource} enum (one primary source + one
 * combustion underlying). Such vehicles are extremely rare in the
 * Floty fleet; can be extended on demand.
 */
final readonly class R2024_017_ConditionalHybridExemption implements ExemptionRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

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

    public function fiscalYear(): int
    {
        return 2024;
    }

    public function name(): string
    {
        return 'Exonération hybride conditionnelle (CO₂)';
    }

    public function description(): string
    {
        return "Véhicules hybrides 2024 respectant des seuils de CO₂ et d'ancienneté - exonération CO₂ totale.";
    }

    public function ruleType(): RuleType
    {
        return RuleType::Exemption;
    }

    public function displayOrder(): int
    {
        return 17;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legalBasis(): array
    {
        return [
            [
                'type' => 'CIBS',
                'article' => 'L. 421-125',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044602969/2024-06-01',
                'consulted_at' => '2026-05-06',
            ],
        ];
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
            $this->ruleCode(),
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

    public function pedagogicalContent(): RulePedagogicalContent
    {
        return new RulePedagogicalContent(
            tab: RuleTab::Calcul,
            section: RuleSection::Exoneration,
            title: 'Exonération hybride conditionnelle (valable 2024 uniquement)',
            pitch: 'Certaines combinaisons hybrides bénéficient d’une exonération CO₂ en 2024, sous conditions de combinaison ET de seuil d’émissions.',
            appliesWhen: "La combinaison de sources d'énergie doit être l'une des suivantes : électrique/hydrogène + gaz/GPL/essence/E85, OU gaz naturel/GPL + essence/E85. Les hybrides Diesel-électrique ne sont PAS éligibles. ET les émissions doivent respecter un seuil : ≤ 60 g/km WLTP (ou ≤ 120 si véhicule < 3 ans au 01/01/2024).",
            effect: 'Taxe CO₂ = 0 € si les deux conditions sont remplies. La taxe polluants reste due selon la catégorie.',
            example: 'Captur E-Tech hybride essence+électrique, WLTP 32 g/km, immat. 2022 (< 3 ans → seuil 120) : 32 ≤ 120 ✓ → exonéré. Classe E 300de hybride Diesel+électrique, WLTP 38 g/km : combinaison Diesel non listée → PAS exonéré.',
        );
    }
}
