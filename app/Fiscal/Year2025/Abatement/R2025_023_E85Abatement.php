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
 * R-2025-023 · ⚠️ NOUVEAUTÉ 2025 · Abattement E85 (CIBS art. L. 421-125
 * version 01/01/2025, modifiée par LOI n°2023-1322 du 29/12/2023 art. 97, 23°).
 *
 * **Mécanique** · pour les véhicules dont la rubrique P.3 du certificat
 * d'immatriculation appartient à la liste opposable BOFiP des 9 codes
 * {FE, FG, FN, FL, FH, FR, FQ, FM, FP} (flag `accepts_e85` côté VFC) :
 * - **WLTP / NEDC** · abattement de **40 %** sur la valeur CO₂ en entrée
 *   du barème, **sauf si CO₂ > 250 g/km**.
 * - **PA** · abattement de **2 CV** sur la puissance, **sauf si PA > 12 CV**.
 *
 * **Pipeline · étape 5** · s'exécute APRÈS les exonérations (étape 4) et
 * AVANT la tarification (étape 6). Si une exonération `fullZeroingTariffs`
 * ou `onlyCo2` est déjà rendue, l'abattement n'a aucun effet pratique
 * (le pricing sera neutralisé / le tarif CO₂ zéroïsé).
 *
 * **Implémentation** · on clone la VFC (Eloquent `replicate()`) avec les
 * valeurs réduites, puis on la réinjecte dans le contexte. Les classes
 * PricingRule (R-2025-010/011/012) liront ces valeurs réduites.
 *
 * **Sources légales auditées Chrome live le 14/05/2026** :
 * - CIBS art. L. 421-125 (Légifrance LEGIARTI000048844564).
 * - BOFiP `BOI-AIS-MOB-10-30-20-20250528` § 240 (doctrine opposable).
 * - BOFiP `BOI-AIS-MOB-10-20-40-20250604` § 160 (liste opposable des 9
 *   codes P.3 du CI · renvoi cross-prélèvement vers malus CO₂).
 *
 * **Lecture stricte des plafonds** · « sauf lorsque ces émissions ou
 * cette puissance dépassent respectivement 250 g/km ou douze chevaux
 * administratifs ». « Dépassent » = strictement supérieur. Donc 250 g/km
 * exactement → abattement applicable ; 251 g/km → non applicable. Idem
 * 12 CV exact = applicable ; 13 CV = non applicable.
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
