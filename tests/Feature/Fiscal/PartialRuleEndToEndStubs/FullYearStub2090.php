<?php

declare(strict_types=1);

namespace Tests\Feature\Fiscal\PartialRuleEndToEndStubs;

use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\FiscalRule;
use Carbon\CarbonImmutable;

/**
 * Stub règle full-year année 2090, utilisée par
 * {@see Tests\Feature\Fiscal\PartialRuleEndToEndTest}.
 *
 * Évite la dépendance à un Year2090Boot persistent dans le config et
 * isole le namespace pour prévenir toute collision avec d'autres stubs.
 */
final readonly class FullYearStub2090 implements FiscalRule
{
    public function ruleCode(): string
    {
        return 'R-2090-E2E-FULLYEAR';
    }

    public function name(): string
    {
        return 'E2E full-year stub 2090';
    }

    public function description(): string
    {
        return 'Stub permanent (test κ.8) : règle full-year 2090.';
    }

    public function ruleType(): RuleType
    {
        return RuleType::Transversal;
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
        return CarbonImmutable::create(2090, 1, 1);
    }

    public function applicabilityEnd(): ?CarbonImmutable
    {
        return CarbonImmutable::create(2090, 12, 31);
    }
}
