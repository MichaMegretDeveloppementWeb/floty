<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\Contracts\Concerns;

use App\Fiscal\Contracts\Concerns\AnnualRuleTrait;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Couvre {@see AnnualRuleTrait} : à partir d'un {@see fiscalYear()}
 * fourni par la classe consommatrice, le trait dérive automatiquement
 * `applicabilityStart` (year-01-01 00:00:00) et `applicabilityEnd`
 * (year-12-31 23:59:59).
 *
 * On utilise un stub local (non public, namespacé Tests/) qui adopte le
 * trait, pour ne pas dépendre des règles fiscales réelles 2024.
 */
final class AnnualRuleTraitTest extends TestCase
{
    #[Test]
    public function applicability_start_est_le_premier_janvier_de_l_annee(): void
    {
        $rule = new StubAnnualRule(2024);

        $start = $rule->applicabilityStart();

        self::assertSame('2024-01-01 00:00:00', $start->format('Y-m-d H:i:s'));
        self::assertInstanceOf(CarbonImmutable::class, $start);
    }

    #[Test]
    public function applicability_end_est_le_trente_et_un_decembre_de_l_annee(): void
    {
        $rule = new StubAnnualRule(2024);

        $end = $rule->applicabilityEnd();

        self::assertNotNull($end);
        self::assertSame('2024-12-31 23:59:59', $end->format('Y-m-d H:i:s'));
        self::assertInstanceOf(CarbonImmutable::class, $end);
    }

    #[Test]
    public function fiscal_year_est_lue_depuis_la_classe_consommatrice(): void
    {
        $rule = new StubAnnualRule(2026);

        self::assertSame(2026, $rule->fiscalYear());
        self::assertSame('2026-01-01 00:00:00', $rule->applicabilityStart()->format('Y-m-d H:i:s'));
        self::assertSame('2026-12-31 23:59:59', $rule->applicabilityEnd()?->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function gere_une_annee_bissextile(): void
    {
        $rule = new StubAnnualRule(2024);

        // 2024 est bissextile - end reste au 31/12 (pas au 29/02).
        self::assertSame('2024-12-31', $rule->applicabilityEnd()?->format('Y-m-d'));
    }
}

/**
 * Stub local qui adopte {@see AnnualRuleTrait} sans implémenter le
 * contrat complet `FiscalRule` (le trait est testé en isolation).
 */
final class StubAnnualRule
{
    use AnnualRuleTrait;

    public function __construct(private readonly int $year) {}

    public function fiscalYear(): int
    {
        return $this->year;
    }
}
