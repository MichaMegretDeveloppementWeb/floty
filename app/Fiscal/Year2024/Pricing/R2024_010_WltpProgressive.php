<?php

declare(strict_types=1);

namespace App\Fiscal\Year2024\Pricing;

use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Enums\Vehicle\HomologationMethod;
use App\Fiscal\Contracts\Concerns\AnnualRuleTrait;
use App\Fiscal\Contracts\PricingRule;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\ValueObjects\BracketRange;
use App\Fiscal\ValueObjects\ProgressiveScale;

/**
 * R-2024-010 - Barème CO₂ WLTP 2024 (CIBS art. L. 421-120).
 *
 * Tarif progressif par tranches à tarif marginal sur les émissions
 * CO₂ WLTP (g/km). 9 tranches, dernière ouverte (≥ 175 g/km).
 *
 * S'exécute uniquement si la méthode CO₂ résolue par
 * {@see R2024_005_Co2MethodSelection} est WLTP. Sinon no-op.
 */
final readonly class R2024_010_WltpProgressive implements PricingRule
{
    use AnnualRuleTrait;

    private ProgressiveScale $scale;

    public function __construct()
    {
        $this->scale = new ProgressiveScale([
            new BracketRange(0, 14, 0.0),
            new BracketRange(14, 55, 1.0),
            new BracketRange(55, 63, 2.0),
            new BracketRange(63, 95, 3.0),
            new BracketRange(95, 115, 4.0),
            new BracketRange(115, 135, 10.0),
            new BracketRange(135, 155, 50.0),
            new BracketRange(155, 175, 60.0),
            new BracketRange(175, null, 65.0),
        ]);
    }

    public function ruleCode(): string
    {
        return 'R-2024-010';
    }

    public function fiscalYear(): int
    {
        return 2024;
    }

    public function name(): string
    {
        return 'Barème WLTP 2024';
    }

    public function description(): string
    {
        return 'Tarif progressif par tranches sur les émissions CO₂ WLTP.';
    }

    public function ruleType(): RuleType
    {
        return RuleType::Tariff;
    }

    public function displayOrder(): int
    {
        return 10;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legalBasis(): array
    {
        return [
            ['type' => 'CIBS', 'article' => 'L. 421-120'],
        ];
    }

    /**
     * @return list<TaxType>
     */
    public function taxesConcerned(): array
    {
        return [TaxType::Co2];
    }

    public function price(PipelineContext $context): PipelineContext
    {
        if ($context->resolvedCo2Method !== HomologationMethod::Wltp) {
            return $context;
        }
        $co2 = $context->currentFiscalCharacteristics->co2_wltp ?? 0;
        $tariff = $this->scale->apply($co2);

        return $context
            ->withCo2FullYearTariff($tariff)
            ->withAppliedRule($this->ruleCode());
    }
}
