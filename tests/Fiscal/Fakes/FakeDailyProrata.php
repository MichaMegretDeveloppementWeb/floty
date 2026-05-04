<?php

declare(strict_types=1);

namespace Tests\Fiscal\Fakes;

use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\TransversalRule;
use App\Fiscal\Pipeline\PipelineContext;

/**
 * Fake `TransversalRule` minimal qui applique un prorata journalier
 * équivalent à R-2024-002 - utilisé pour valider que le pipeline
 * tourne sur une année arbitraire (cf.
 * `tests/Feature/Fiscal/FiscalRegistryExtensibilityTest.php`).
 */
final readonly class FakeDailyProrata implements TransversalRule
{
    public function ruleCode(): string
    {
        return 'R-2099-FAKE-PRORATA';
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
        $co2 = ($context->co2FullYearTariff ?? 0.0) * $context->daysAssignedToCompany / max($context->daysInYear, 1);
        $pollutants = ($context->pollutantsFullYearTariff ?? 0.0) * $context->daysAssignedToCompany / max($context->daysInYear, 1);

        return $context
            ->withDueAmounts($co2, $pollutants)
            ->withAppliedRule($this->ruleCode());
    }
}
