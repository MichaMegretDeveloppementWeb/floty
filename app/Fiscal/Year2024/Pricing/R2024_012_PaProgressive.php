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
 * R-2024-012 — Barème Puissance Administrative 2024 (CIBS L. 421-122).
 *
 * Barème progressif sur la puissance administrative en chevaux fiscaux
 * (CV). Fallback historique pour les véhicules sans donnée CO₂. 5
 * tranches, dernière ouverte (> 15 CV). S'exécute uniquement si la
 * méthode CO₂ résolue est PA.
 */
final readonly class R2024_012_PaProgressive implements PricingRule
{
    private ProgressiveScale $scale;

    public function __construct()
    {
        $this->scale = new ProgressiveScale([
            new BracketRange(0, 3, 1500.0),
            new BracketRange(3, 6, 2250.0),
            new BracketRange(6, 10, 3750.0),
            new BracketRange(10, 15, 4750.0),
            new BracketRange(15, null, 6000.0),
        ]);
    }

    public function ruleCode(): string
    {
        return 'R-2024-012';
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
        if ($context->resolvedCo2Method !== HomologationMethod::Pa) {
            return $context;
        }
        $cv = $context->currentFiscalCharacteristics?->taxable_horsepower ?? 0;
        $tariff = $this->scale->apply($cv);

        return $context
            ->withCo2FullYearTariff($tariff)
            ->withAppliedRule($this->ruleCode());
    }
}
