<?php

declare(strict_types=1);

namespace Tests\Unit\Services\FiscalRule;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\ExemptionRule;
use App\Fiscal\Contracts\FiscalRule;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\Registry\FiscalRuleRegistry;
use App\Fiscal\ValueObjects\ExemptionVerdict;
use App\Fiscal\ValueObjects\RulePedagogicalContent;
use App\Services\FiscalRule\FiscalRuleQueryService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre le service de lecture des règles fiscales pour la page
 * « Règles de calcul » (Phase 13 D5.13 · ADR-0022 finalisée v1.3).
 *
 * **Stratégie post-D5.13** · le service consomme désormais le registry
 * (les classes PHP) au lieu de la table `fiscal_rules`. Les tests
 * enregistrent des stubs runtime sur une année factice (2099) pour
 * isoler le comportement du service sans dépendre des 24 règles 2024
 * réelles. Cette technique est éprouvée par `PartialRuleEndToEndTest`.
 */
final class FiscalRuleQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    private FiscalRuleQueryService $service;

    private FiscalRuleRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(FiscalRuleQueryService::class);
        $this->registry = $this->app->make(FiscalRuleRegistry::class);
    }

    #[Test]
    public function trie_par_display_order_via_le_suffixe_numerique_du_rule_code(): void
    {
        $this->registry->register(2099, [
            StubRule2099_005::class,
            StubRule2099_001::class,
            StubRule2099_010::class,
        ]);

        $result = $this->service->listForYear(2099)->toArray();

        self::assertCount(3, $result);
        self::assertSame('R-2099-001', $result[0]['ruleCode']);
        self::assertSame('R-2099-005', $result[1]['ruleCode']);
        self::assertSame('R-2099-010', $result[2]['ruleCode']);
    }

    #[Test]
    public function regle_full_year_expose_dates_clippees_et_is_full_year_true(): void
    {
        $this->registry->register(2099, [StubFullYearRule2099::class]);

        $result = $this->service->listForYear(2099)->toArray();

        self::assertCount(1, $result);
        self::assertSame('2099-01-01', $result[0]['applicabilityStartInYear']);
        self::assertSame('2099-12-31', $result[0]['applicabilityEndInYear']);
        self::assertTrue($result[0]['isFullYear']);
    }

    #[Test]
    public function regle_partielle_a_dates_clippees_et_is_full_year_false(): void
    {
        $this->registry->register(2099, [StubPartialMidYearRule2099::class]);

        $result = $this->service->listForYear(2099)->toArray();

        self::assertSame('2099-07-01', $result[0]['applicabilityStartInYear']);
        self::assertSame('2099-12-31', $result[0]['applicabilityEndInYear']);
        self::assertFalse($result[0]['isFullYear']);
    }

    #[Test]
    public function regle_open_ended_clippe_l_end_a_la_fin_de_l_annee(): void
    {
        $this->registry->register(2099, [StubOpenEndedRule2099::class]);

        $result = $this->service->listForYear(2099)->toArray();

        self::assertSame('2099-12-31', $result[0]['applicabilityEndInYear']);
        self::assertTrue($result[0]['isFullYear']);
    }
}

/**
 * Trait commun aux stubs · réduit le boilerplate dans chaque classe.
 */
trait FiscalRuleQueryServiceTestStub
{
    public function name(): string
    {
        return 'Stub';
    }

    public function description(): string
    {
        return 'Stub de test.';
    }

    public function ruleType(): RuleType
    {
        return RuleType::Exemption;
    }

    public function taxesConcerned(): array
    {
        return [TaxType::Co2];
    }

    public function legalBasis(): array
    {
        return [];
    }

    public function displayOrder(): int
    {
        return 0;
    }

    public function isActive(): bool
    {
        return true;
    }

    public function pedagogicalContent(): RulePedagogicalContent
    {
        return new RulePedagogicalContent(
            tab: RuleTab::Calcul,
            section: RuleSection::Bareme,
            title: 'Stub',
            pitch: 'Stub utilisé en tests · non rendu en UI.',
        );
    }

    public function evaluate(PipelineContext $context): ExemptionVerdict
    {
        return ExemptionVerdict::notExempt();
    }
}

final readonly class StubRule2099_001 implements ExemptionRule, FiscalRule
{
    use FiscalRuleQueryServiceTestStub;

    public function ruleCode(): string
    {
        return 'R-2099-001';
    }

    public function applicabilityStart(): CarbonImmutable
    {
        return CarbonImmutable::create(2099, 1, 1);
    }

    public function applicabilityEnd(): ?CarbonImmutable
    {
        return CarbonImmutable::create(2099, 12, 31);
    }
}

final readonly class StubRule2099_005 implements ExemptionRule, FiscalRule
{
    use FiscalRuleQueryServiceTestStub;

    public function ruleCode(): string
    {
        return 'R-2099-005';
    }

    public function applicabilityStart(): CarbonImmutable
    {
        return CarbonImmutable::create(2099, 1, 1);
    }

    public function applicabilityEnd(): ?CarbonImmutable
    {
        return CarbonImmutable::create(2099, 12, 31);
    }
}

final readonly class StubRule2099_010 implements ExemptionRule, FiscalRule
{
    use FiscalRuleQueryServiceTestStub;

    public function ruleCode(): string
    {
        return 'R-2099-010';
    }

    public function applicabilityStart(): CarbonImmutable
    {
        return CarbonImmutable::create(2099, 1, 1);
    }

    public function applicabilityEnd(): ?CarbonImmutable
    {
        return CarbonImmutable::create(2099, 12, 31);
    }
}

final readonly class StubFullYearRule2099 implements ExemptionRule, FiscalRule
{
    use FiscalRuleQueryServiceTestStub;

    public function ruleCode(): string
    {
        return 'R-2099-100';
    }

    public function applicabilityStart(): CarbonImmutable
    {
        return CarbonImmutable::create(2099, 1, 1);
    }

    public function applicabilityEnd(): ?CarbonImmutable
    {
        return CarbonImmutable::create(2099, 12, 31);
    }
}

final readonly class StubPartialMidYearRule2099 implements ExemptionRule, FiscalRule
{
    use FiscalRuleQueryServiceTestStub;

    public function ruleCode(): string
    {
        return 'R-2099-200';
    }

    public function applicabilityStart(): CarbonImmutable
    {
        return CarbonImmutable::create(2099, 7, 1);
    }

    public function applicabilityEnd(): ?CarbonImmutable
    {
        return CarbonImmutable::create(2099, 12, 31);
    }
}

final readonly class StubOpenEndedRule2099 implements ExemptionRule, FiscalRule
{
    use FiscalRuleQueryServiceTestStub;

    public function ruleCode(): string
    {
        return 'R-2099-300';
    }

    public function applicabilityStart(): CarbonImmutable
    {
        return CarbonImmutable::create(2099, 1, 1);
    }

    public function applicabilityEnd(): ?CarbonImmutable
    {
        return null;
    }
}
