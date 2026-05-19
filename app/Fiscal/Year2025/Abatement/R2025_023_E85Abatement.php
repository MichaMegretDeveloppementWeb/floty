<?php

declare(strict_types=1);

namespace App\Fiscal\Year2025\Abatement;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Enums\Vehicle\HomologationMethod;
use App\Fiscal\Contracts\AbatementRule;
use App\Fiscal\Contracts\Concerns\AnnualRuleTrait;
use App\Fiscal\Contracts\Concerns\RuleActiveByDefaultTrait;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\ValueObjects\RulePedagogicalContent;
use App\Models\VehicleFiscalCharacteristics;

/**
 * R-2025-023 - 2025 new feature: E85 abatement (CIBS art. L. 421-125
 * version 01/01/2025, modified by LOI n°2023-1322 du 29/12/2023 art. 97, 23°).
 *
 * Mechanics: for vehicles whose registration certificate P.3 entry
 * belongs to the BOFiP-mandated list of 9 codes
 * {FE, FG, FN, FL, FH, FR, FQ, FM, FP} (`accepts_e85` flag on VFC):
 * - WLTP / NEDC: 40% abatement on the CO₂ value at scale entry,
 *   unless CO₂ > 250 g/km.
 * - PA: 2 CV abatement on horsepower, unless PA > 12 CV.
 *
 * Pipeline step 5: runs AFTER exemptions (step 4) and BEFORE pricing
 * (step 6). If a `fullZeroingTariffs` or `onlyCo2` exemption has
 * already been issued, the abatement has no practical effect (pricing
 * will be neutralised / CO₂ tariff zeroed).
 *
 * Implementation: the VFC is cloned (Eloquent `replicate()`) with
 * reduced values and re-injected into the context. PricingRule classes
 * (R-2025-010/011/012) will read those reduced values.
 *
 * Strict reading of the caps: "sauf lorsque ces émissions ou cette
 * puissance dépassent respectivement 250 g/km ou douze chevaux
 * administratifs". "Dépassent" = strictly greater than. So 250 g/km
 * exactly → abatement applicable; 251 g/km → not applicable. Same
 * applies for 12 CV exact = applicable; 13 CV = not applicable.
 */
final readonly class R2025_023_E85Abatement implements AbatementRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    private const float WLTP_NEDC_REDUCTION_FACTOR = 0.60;

    private const int CO2_THRESHOLD_INCLUSIVE = 250;

    private const int PA_REDUCTION_CV = 2;

    private const int PA_THRESHOLD_INCLUSIVE = 12;

    public function ruleCode(): string
    {
        return 'R-2025-023';
    }

    public function fiscalYear(): int
    {
        return 2025;
    }

    public function name(): string
    {
        return 'Abattement E85 (nouveauté 2025)';
    }

    public function description(): string
    {
        return "Pour les véhicules dont la source d'énergie comprend le superéthanol E85 (rubrique P.3 du CI ∈ {FE, FG, FN, FL, FH, FR, FQ, FM, FP}) · abattement de 40 % sur les émissions CO₂ (WLTP/NEDC) sous plafond 250 g/km, OU de 2 CV sur la puissance administrative sous plafond 12 CV. Modifie la base d'entrée des barèmes CO₂ avant tarification. Nouveauté 2025 (CIBS L. 421-125 réformé par LF 2024 art. 97, 23°).";
    }

    public function ruleType(): RuleType
    {
        return RuleType::Abatement;
    }

    public function displayOrder(): int
    {
        return 23;
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
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000048844564/2025-01-01',
                'consulted_at' => '2026-05-13',
            ],
            [
                'type' => 'BOFIP',
                'reference' => 'BOI-AIS-MOB-10-30-20',
                'paragraph' => '§ 240',
                'url' => 'https://bofip.impots.gouv.fr/bofip/13954-PGP.html/identifiant=BOI-AIS-MOB-10-30-20-20250528',
                'consulted_at' => '2026-05-14',
            ],
            [
                'type' => 'BOFIP',
                'reference' => 'BOI-AIS-MOB-10-20-40',
                'paragraph' => '§ 160',
                'url' => 'https://bofip.impots.gouv.fr/bofip/13927-PGP.html/identifiant=BOI-AIS-MOB-10-20-40-20250604',
                'consulted_at' => '2026-05-14',
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

    public function abate(PipelineContext $context): PipelineContext
    {
        $vfc = $context->currentFiscalCharacteristics;
        if ($vfc === null || $vfc->accepts_e85 !== true) {
            return $context;
        }

        $method = $context->resolvedCo2Method;
        if ($method === null) {
            return $context;
        }

        return match ($method) {
            HomologationMethod::Wltp => $this->abateWltp($context, $vfc),
            HomologationMethod::Nedc => $this->abateNedc($context, $vfc),
            HomologationMethod::Pa => $this->abatePa($context, $vfc),
        };
    }

    private function abateWltp(PipelineContext $context, VehicleFiscalCharacteristics $vfc): PipelineContext
    {
        $co2 = $vfc->co2_wltp;
        if ($co2 === null || $co2 > self::CO2_THRESHOLD_INCLUSIVE) {
            return $context;
        }

        $reduced = $vfc->replicate();
        $reduced->co2_wltp = (int) round($co2 * self::WLTP_NEDC_REDUCTION_FACTOR);

        return $context
            ->withCurrentFiscalCharacteristics($reduced)
            ->withAppliedRule($this->ruleCode());
    }

    private function abateNedc(PipelineContext $context, VehicleFiscalCharacteristics $vfc): PipelineContext
    {
        $co2 = $vfc->co2_nedc;
        if ($co2 === null || $co2 > self::CO2_THRESHOLD_INCLUSIVE) {
            return $context;
        }

        $reduced = $vfc->replicate();
        $reduced->co2_nedc = (int) round($co2 * self::WLTP_NEDC_REDUCTION_FACTOR);

        return $context
            ->withCurrentFiscalCharacteristics($reduced)
            ->withAppliedRule($this->ruleCode());
    }

    private function abatePa(PipelineContext $context, VehicleFiscalCharacteristics $vfc): PipelineContext
    {
        $pa = $vfc->taxable_horsepower;
        if ($pa === null || $pa > self::PA_THRESHOLD_INCLUSIVE) {
            return $context;
        }

        $reduced = $vfc->replicate();
        $reduced->taxable_horsepower = max(0, $pa - self::PA_REDUCTION_CV);

        return $context
            ->withCurrentFiscalCharacteristics($reduced)
            ->withAppliedRule($this->ruleCode());
    }

    public function pedagogicalContent(): RulePedagogicalContent
    {
        return new RulePedagogicalContent(
            tab: RuleTab::Calcul,
            section: RuleSection::Exoneration,
            title: 'Abattement E85 (nouveauté 2025)',
            pitch: 'Les véhicules pouvant rouler au superéthanol E85 bénéficient d\'un abattement de 40 % sur les émissions CO₂ ou de 2 CV sur la puissance administrative, sous plafonds.',
            appliesWhen: "La rubrique P.3 du certificat d'immatriculation appartient à la liste opposable BOFiP des 9 codes : FE, FG, FN, FL, FH, FR, FQ, FM, FP (flag accepts_e85 = true côté l'application).",
            effect: 'WLTP/NEDC · 40 % d\'abattement sur les émissions CO₂, sauf si > 250 g/km. PA · 2 CV soustraits, sauf si > 12 CV. L\'abattement modifie la base d\'entrée du barème CO₂ avant la tarification. La taxe polluants n\'est pas concernée.',
            example: 'Renault Captur flex-fuel E85 WLTP 130 g/km en 2025 : co2_retenu = 130 × 0,60 = 78 g/km. Barème 2025 sur 78 g/km = 117 € (vs 433 € sans abattement, économie 316 €/an).',
        );
    }
}
