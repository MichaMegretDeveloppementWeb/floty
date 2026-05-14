<?php

declare(strict_types=1);

namespace App\Fiscal\Year2024\Exemption;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Enums\Vehicle\EnergySource;
use App\Fiscal\Contracts\Concerns\AnnualRuleTrait;
use App\Fiscal\Contracts\Concerns\RuleActiveByDefaultTrait;
use App\Fiscal\Contracts\ExemptionRule;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\ValueObjects\ExemptionVerdict;
use App\Fiscal\ValueObjects\RulePedagogicalContent;

/**
 * R-2024-016 - Exonération CO₂ électrique / hydrogène (CIBS L. 421-124).
 *
 * Véhicules à motorisation strictement électrique, hydrogène ou
 * combinaison électrique + hydrogène : exonération **CO₂ uniquement**
 * (la taxe polluants reste due si le véhicule n'est pas catégorie E).
 *
 * Sémantique préservée : le tarif CO₂ annuel plein est mis à 0 dans le
 * breakdown (au lieu d'être calculé puis non appliqué).
 */
final readonly class R2024_016_ElectricHydrogen implements ExemptionRule
{
    use AnnualRuleTrait;
    use RuleActiveByDefaultTrait;

    public function ruleCode(): string
    {
        return 'R-2024-016';
    }

    public function fiscalYear(): int
    {
        return 2024;
    }

    public function name(): string
    {
        return 'Exonération électrique / hydrogène (CO₂)';
    }

    public function description(): string
    {
        return 'Véhicules électriques, hydrogène, ou électrique + hydrogène exclusifs - exonération totale CO₂.';
    }

    public function ruleType(): RuleType
    {
        return RuleType::Exemption;
    }

    public function displayOrder(): int
    {
        return 16;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legalBasis(): array
    {
        return [
            [
                'type' => 'CIBS',
                'article' => 'L. 421-124',
                'url' => 'https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000044602971/2024-06-01',
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
        $energy = $context->currentFiscalCharacteristics?->energy_source;
        if ($energy === null) {
            return ExemptionVerdict::notExempt();
        }

        $isElectric = match ($energy) {
            EnergySource::Electric,
            EnergySource::Hydrogen,
            EnergySource::ElectricHydrogen => true,
            default => false,
        };

        if ($isElectric) {
            return ExemptionVerdict::onlyCo2(
                'Exonération électrique/hydrogène (CIBS L. 421-124)',
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
            title: 'Exonération électrique / hydrogène',
            pitch: 'Véhicules 100 % électriques ou hydrogène : exonération totale de la taxe CO₂.',
            appliesWhen: "La source d'énergie est exclusivement électrique, hydrogène, ou une combinaison des deux. Toute source complémentaire (essence, gaz…) sortirait du champ.",
            effect: "Taxe CO₂ = 0 € au titre de l'article L. 421-124. Pour les polluants, le véhicule est catégorie E → 0 € par effet du barème (voir R-2024-014).",
            example: 'Tesla Model 3 électrique, utilisée 366/366 jours par ACME : taxe CO₂ 0 € + taxe polluants 0 € = total 0,00 €.',
        );
    }
}
