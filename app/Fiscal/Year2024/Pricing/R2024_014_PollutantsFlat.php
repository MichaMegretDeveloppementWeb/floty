<?php

declare(strict_types=1);

namespace App\Fiscal\Year2024\Pricing;

use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Enums\Vehicle\PollutantCategory;
use App\Fiscal\Contracts\Concerns\AnnualRuleTrait;
use App\Fiscal\Contracts\PricingRule;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\ValueObjects\PollutantTariff;

/**
 * R-2024-014 - Tarif annuel forfaitaire polluants (CIBS L. 421-135).
 *
 *   - E (électrique / hydrogène)        →   0 €
 *   - Catégorie 1 (essence/gaz Euro 5/6)→ 100 €
 *   - Plus polluants                    → 500 €
 *
 * La catégorie elle-même est résolue à partir des caractéristiques
 * fiscales (champ `pollutant_category` stocké, posé sur le contexte
 * par {@see R2024_005_Co2MethodSelection}).
 */
final readonly class R2024_014_PollutantsFlat implements PricingRule
{
    use AnnualRuleTrait;

    private PollutantTariff $tariff;

    public function __construct()
    {
        $this->tariff = new PollutantTariff([
            PollutantCategory::E->value => 0.0,
            PollutantCategory::Category1->value => 100.0,
            PollutantCategory::MostPolluting->value => 500.0,
        ]);
    }

    public function ruleCode(): string
    {
        return 'R-2024-014';
    }

    public function fiscalYear(): int
    {
        return 2024;
    }

    public function name(): string
    {
        return 'Tarif forfaitaire polluants 2024';
    }

    public function description(): string
    {
        return "Tarif annuel forfaitaire par catégorie d'émissions (E = 0 € / 1 = 100 € / plus polluants = 500 €).";
    }

    public function ruleType(): RuleType
    {
        return RuleType::Tariff;
    }

    public function displayOrder(): int
    {
        return 14;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legalBasis(): array
    {
        return [
            ['type' => 'CIBS', 'article' => 'L. 421-135'],
        ];
    }

    /**
     * @return list<TaxType>
     */
    public function taxesConcerned(): array
    {
        return [TaxType::Pollutants];
    }

    public function price(PipelineContext $context): PipelineContext
    {
        $category = $context->resolvedPollutantCategory;
        if ($category === null) {
            return $context;
        }

        return $context
            ->withPollutantsFullYearTariff($this->tariff->tariffFor($category))
            ->withAppliedRule($this->ruleCode());
    }
}
