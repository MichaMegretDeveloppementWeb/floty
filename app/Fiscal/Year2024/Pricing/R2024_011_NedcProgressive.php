<?php

declare(strict_types=1);

namespace App\Fiscal\Year2024\Pricing;

use App\Enums\Fiscal\TaxType;
use App\Enums\Vehicle\HomologationMethod;
use App\Fiscal\Contracts\PricingRule;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\ValueObjects\BracketRange;
use App\Fiscal\ValueObjects\ProgressiveScale;

/**
 * R-2024-011 — Barème CO₂ NEDC 2024 (CIBS art. L. 421-121).
 *
 * Barème progressif sur les émissions CO₂ NEDC (g/km). Concerne les
 * véhicules antérieurs à WLTP. 9 tranches, dernière ouverte
 * (≥ 145 g/km). S'exécute uniquement si la méthode CO₂ résolue est
 * NEDC.
 */
final readonly class R2024_011_NedcProgressive implements PricingRule
{
    private ProgressiveScale $scale;

    public function __construct()
    {
        $this->scale = new ProgressiveScale([
            new BracketRange(0, 12, 0.0),
            new BracketRange(12, 45, 1.0),
            new BracketRange(45, 52, 2.0),
            new BracketRange(52, 79, 3.0),
            new BracketRange(79, 95, 4.0),
            new BracketRange(95, 112, 10.0),
            new BracketRange(112, 128, 50.0),
            new BracketRange(128, 145, 60.0),
            new BracketRange(145, null, 65.0),
        ]);
    }

    public function ruleCode(): string
    {
        return 'R-2024-011';
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
        if ($context->resolvedCo2Method !== HomologationMethod::Nedc) {
            return $context;
        }
        $co2 = $context->currentFiscalCharacteristics?->co2_nedc ?? 0;
        $tariff = $this->scale->apply($co2);

        return $context
            ->withCo2FullYearTariff($tariff)
            ->withAppliedRule($this->ruleCode());
    }
}
