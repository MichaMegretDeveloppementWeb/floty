<?php

declare(strict_types=1);

namespace App\Fiscal\Year2024\Transversal;

use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\TransversalRule;
use App\Fiscal\Pipeline\PipelineContext;

/**
 * R-2024-003 — Arrondi half-up commercial (CIBS L. 131-1).
 *
 * V1 — sémantique préservée pour compat goldens : on arrondit chaque
 * montant intermédiaire (CO₂ et Polluants) à 2 décimales. La règle
 * stricte BOFiP est « un seul arrondi en fin de calcul par redevable »
 * (sur la somme totale par entreprise), à l'euro près. Le fix complet
 * sera traité en phase 1.9 quand l'agrégation par redevable sera
 * implémentée côté Action de déclaration (phase 11).
 */
final readonly class R2024_003_FinalRounding implements TransversalRule
{
    public function ruleCode(): string
    {
        return 'R-2024-003';
    }

    /**
     * @return list<TaxType>
     */
    public function taxesConcerned(): array
    {
        return [TaxType::Co2, TaxType::Pollutants];
    }

    public function apply(PipelineContext $context): PipelineContext
    {
        $co2 = $this->roundHalfUp($context->co2Due ?? 0.0);
        $pollutants = $this->roundHalfUp($context->pollutantsDue ?? 0.0);

        return $context
            ->withDueAmounts($co2, $pollutants)
            ->withAppliedRule($this->ruleCode());
    }

    private function roundHalfUp(float $value): float
    {
        return round($value, 2, PHP_ROUND_HALF_UP);
    }
}
