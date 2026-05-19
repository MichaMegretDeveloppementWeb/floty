<?php

declare(strict_types=1);

namespace App\Fiscal\Year2026\Exemption;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\Concerns\AnnualRuleTrait;
use App\Fiscal\Contracts\Concerns\RuleActiveByDefaultTrait;
use App\Fiscal\Contracts\ExemptionRule;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\ValueObjects\ExemptionVerdict;
use App\Fiscal\ValueObjects\RulePedagogicalContent;

/**
 * R-2026-015 - Disability access exemption (CIBS L. 421-123 /
 * L. 421-136), strict reproduction of R-2025-015. Articles stable
 * since 01/01/2022 and not touched by Ordo 2025-1247.
 *
 * Wheelchair-accessible vehicles: TOTAL exemption from both taxes AND
 * the full annual tariffs are zeroed in the breakdown
 * (`fullZeroingTariffs` semantics preserved).
 */
final readonly class R2026_015_HandicapAccess implements ExemptionRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    public function ruleCode(): string
    {
        return 'R-2026-015';
    }

    public function fiscalYear(): int
    {
        return 2026;
    }

    public function name(): string
    {
        return 'Exonération handicap';
    }

    public function description(): string
    {
        return 'Véhicules accessibles en fauteuil roulant : exonération totale CO₂ et polluants. Texte identique pour les deux taxes (L. 421-136 reprend L. 421-123 mot pour mot). Reconduction stricte 2025.';
    }

    public function ruleType(): RuleType
    {
        return RuleType::Exemption;
    }

    public function displayOrder(): int
    {
        return 15;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legalBasis(): array
    {
        return [
            [
                'type' => 'CIBS',
                'article' => 'L. 421-123',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044602975/2026-01-01',
                'consulted_at' => '2026-05-15',
            ],
        ];
    }

    /**
     * @return list<TaxType>
     */
    public function taxesConcerned(): array
    {
        return [TaxType::Co2, TaxType::Pollutants];
    }

    public function evaluate(PipelineContext $context): ExemptionVerdict
    {
        if ($context->currentFiscalCharacteristics?->handicap_access === true) {
            return ExemptionVerdict::fullZeroingTariffs(
                'Exonération handicap (CIBS L. 421-123 / L. 421-136)',
                $this->ruleCode(),
            );
        }

        return ExemptionVerdict::notExempt();
    }

    public function pedagogicalContent(): RulePedagogicalContent
    {
        return new RulePedagogicalContent(
            tab: RuleTab::Calcul,
            section: RuleSection::Exoneration,
            title: 'Exonération handicap',
            pitch: 'Véhicules M1 accessibles en fauteuil roulant ou aménagés pour conduite par personne handicapée : exonération totale des deux taxes.',
            appliesWhen: "Le véhicule est catégorie M1 ET porte mention d'accessibilité fauteuil roulant ou d'aménagement handicap au certificat d'immatriculation (rubrique J.3).",
            effect: 'Taxe CO₂ = 0 € ET taxe polluants = 0 €. L\'exonération est attachée au véhicule, pas à l\'usage : pas de prorata, même pour une utilisation partielle.',
            example: 'Renault Kangoo M1 accessible fauteuil roulant, Diesel Euro 6, utilisé 274 jours en 2026 : sans exonération on paierait ~488 € de polluants pondéré + taxe CO₂. Avec exonération : 0,00 €.',
        );
    }
}
