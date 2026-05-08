<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\ValueObjects;

use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\Concerns\AnnualRuleTrait;
use App\Fiscal\Contracts\FiscalRule;
use App\Fiscal\ValueObjects\RuleEffectiveSegment;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Couvre le VO {@see RuleEffectiveSegment} (chantier κ.3).
 *
 * Le VO est un simple porteur de données (start, end, rules). Tests
 * minimaux : construction, exposition des champs, immutabilité (héritée
 * de `final readonly`).
 */
final class RuleEffectiveSegmentTest extends TestCase
{
    #[Test]
    public function expose_start_end_et_liste_de_regles(): void
    {
        $start = CarbonImmutable::create(2026, 7, 1);
        $end = CarbonImmutable::create(2026, 12, 31);
        $rule = new RuleEffectiveSegmentStubRule;

        $segment = new RuleEffectiveSegment(
            start: $start,
            end: $end,
            rules: [$rule],
        );

        self::assertSame($start, $segment->start);
        self::assertSame($end, $segment->end);
        self::assertSame([$rule], $segment->rules);
    }

    #[Test]
    public function preserve_l_ordre_des_regles_passees(): void
    {
        $a = new RuleEffectiveSegmentStubRule('R-A');
        $b = new RuleEffectiveSegmentStubRule('R-B');
        $c = new RuleEffectiveSegmentStubRule('R-C');

        $segment = new RuleEffectiveSegment(
            start: CarbonImmutable::create(2026, 1, 1),
            end: CarbonImmutable::create(2026, 12, 31),
            rules: [$a, $b, $c],
        );

        self::assertSame(['R-A', 'R-B', 'R-C'], array_map(
            static fn (FiscalRule $r): string => $r->ruleCode(),
            $segment->rules,
        ));
    }
}

/**
 * Stub local : règle annuelle 2026 minimaliste pour les tests du VO.
 * On n'implémente pas une sous-interface (Pricing/Classification…) car
 * le VO ne dépend que du contrat de base {@see FiscalRule}.
 */
final class RuleEffectiveSegmentStubRule implements FiscalRule
{
    use AnnualRuleTrait;

    public function __construct(private readonly string $code = 'R-2026-STUB') {}

    public function ruleCode(): string
    {
        return $this->code;
    }

    public function fiscalYear(): int
    {
        return 2026;
    }

    /**
     * @return list<TaxType>
     */
    public function taxesConcerned(): array
    {
        return [];
    }

    public function name(): string
    {
        return 'Stub';
    }

    public function description(): string
    {
        return 'Stub';
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
}
