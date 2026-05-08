<?php

declare(strict_types=1);

namespace Tests\Feature\Fiscal\PartialRuleEndToEndStubs;

use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\FiscalRule;
use Carbon\CarbonImmutable;

/**
 * Stub règle partielle (mid-year) année 2090, apparait au 01/07/2090.
 * Utilisée par {@see Tests\Feature\Fiscal\PartialRuleEndToEndTest}.
 */
final readonly class PartialMidYearStub2090 implements FiscalRule
{
    public function ruleCode(): string
    {
        return 'R-2090-E2E-PARTIAL';
    }

    public function name(): string
    {
        return 'E2E partial mid-year stub 2090';
    }

    public function description(): string
    {
        return 'Stub permanent (test κ.8) : règle partielle 01/07/2090 → 31/12/2090.';
    }

    public function ruleType(): RuleType
    {
        return RuleType::Tariff;
    }

    public function displayOrder(): int
    {
        return 2;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legalBasis(): array
    {
        return [];
    }

    public function isActive(): bool
    {
        return true;
    }

    /**
     * @return list<TaxType>
     */
    public function taxesConcerned(): array
    {
        return [TaxType::Co2];
    }

    public function applicabilityStart(): CarbonImmutable
    {
        return CarbonImmutable::create(2090, 7, 1);
    }

    public function applicabilityEnd(): ?CarbonImmutable
    {
        return CarbonImmutable::create(2090, 12, 31);
    }
}
