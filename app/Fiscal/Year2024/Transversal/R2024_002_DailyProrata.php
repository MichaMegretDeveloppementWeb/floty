<?php

declare(strict_types=1);

namespace App\Fiscal\Year2024\Transversal;

use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\TransversalRule;
use App\Fiscal\Pipeline\PipelineContext;

/**
 * R-2024-002 — Prorata journalier (jours / dénominateur dynamique
 * selon l'année, 366 en 2024 bissextile).
 *
 * Applique le prorata aux tarifs annuels pleins déjà calculés par les
 * règles `Pricing` et déjà éventuellement neutralisés par les règles
 * `Exemption` (cf. {@see FiscalPipeline}).
 *
 * Cette règle ne s'occupe PAS de l'arrondi — c'est le rôle de
 * {@see R2024_003_FinalRounding}.
 */
final readonly class R2024_002_DailyProrata implements TransversalRule
{
    public function ruleCode(): string
    {
        return 'R-2024-002';
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
        $co2Full = $context->co2FullYearTariff ?? 0.0;
        $pollutantsFull = $context->pollutantsFullYearTariff ?? 0.0;
        $days = $context->daysAssignedToCompany;
        $denominator = $context->daysInYear;

        $co2Due = $denominator > 0 ? $co2Full * $days / $denominator : 0.0;
        $pollutantsDue = $denominator > 0 ? $pollutantsFull * $days / $denominator : 0.0;

        return $context
            ->withDueAmounts($co2Due, $pollutantsDue)
            ->withAppliedRule($this->ruleCode());
    }
}
