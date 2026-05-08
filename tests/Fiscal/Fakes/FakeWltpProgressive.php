<?php

declare(strict_types=1);

namespace Tests\Fiscal\Fakes;

use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\Concerns\AnnualRuleTrait;
use App\Fiscal\Contracts\PricingRule;
use App\Fiscal\Pipeline\PipelineContext;

/**
 * Fake `PricingRule` utilisé par
 * `tests/Feature/Fiscal/FiscalRegistryExtensibilityTest.php` pour
 * prouver que le registry accepte une année arbitraire (au-delà des
 * `Year2024/...` enregistrées en production).
 *
 * Pose un `co2FullYearTariff` constant - pas de logique réaliste.
 * **Ne jamais référencer cette classe depuis le code de production.**
 */
final readonly class FakeWltpProgressive implements PricingRule
{
    use AnnualRuleTrait;

    public const float FAKE_TARIFF = 1234.0;

    public function ruleCode(): string
    {
        return 'R-2099-FAKE-WLTP';
    }

    public function fiscalYear(): int
    {
        return 2099;
    }

    /**
     * @return list<TaxType>
     */
    public function taxesConcerned(): array
    {
        return [TaxType::Co2];
    }

    public function name(): string
    {
        return 'Fake WLTP Progressive 2099';
    }

    public function description(): string
    {
        return 'Fake pricing rule for FiscalRegistryExtensibilityTest only.';
    }

    public function ruleType(): RuleType
    {
        return RuleType::Tariff;
    }

    public function displayOrder(): int
    {
        return 1;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legalBasis(): array
    {
        return [];
    }

    public function price(PipelineContext $context): PipelineContext
    {
        return $context
            ->withCo2FullYearTariff(self::FAKE_TARIFF)
            ->withAppliedRule($this->ruleCode());
    }
}
