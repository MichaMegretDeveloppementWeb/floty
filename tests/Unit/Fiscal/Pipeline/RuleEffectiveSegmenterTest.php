<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\Pipeline;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Exceptions\Fiscal\FiscalCalculationException;
use App\Fiscal\Contracts\FiscalRule;
use App\Fiscal\Pipeline\RuleEffectiveSegmenter;
use App\Fiscal\Registry\FiscalRuleRegistry;
use App\Fiscal\ValueObjects\RulePedagogicalContent;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre {@see RuleEffectiveSegmenter} (chantier κ.3).
 *
 * Les tests utilisent une **année stub 2090** non enregistrée en
 * production : pour chaque cas, on enregistre des classes anonymes
 * implémentant `FiscalRule` avec des bornes d'applicabilité spécifiques,
 * et on vérifie le découpage produit.
 *
 * Pour permettre l'enregistrement de classes anonymes (qui n'ont pas de
 * `class-string` stable), on bypass le constructeur par défaut du
 * registry et on **subcontracte** un registry custom : un sous-classe de
 * `FiscalRuleRegistry` ne nous aiderait pas car le registry instancie
 * via le container. On utilise donc des **classes nommées** déclarées
 * dans ce fichier, enregistrées par leur `::class`.
 */
final class RuleEffectiveSegmenterTest extends TestCase
{
    /**
     * Année stub réservée aux tests de ce fichier (non enregistrée en
     * production via `floty.fiscal.year_boots`).
     */
    private const int STUB_YEAR = 2090;

    private FiscalRuleRegistry $registry;

    private RuleEffectiveSegmenter $segmenter;

    protected function setUp(): void
    {
        parent::setUp();

        // Le registry est un singleton applicatif - on le récupère et on
        // y enregistre l'année stub à la volée. Le segmenteur doit être
        // ré-instancié pour repartir d'un cache propre à chaque test.
        $this->registry = $this->app->make(FiscalRuleRegistry::class);
        $this->app->forgetInstance(RuleEffectiveSegmenter::class);
        $this->segmenter = $this->app->make(RuleEffectiveSegmenter::class);
    }

    #[Test]
    public function annee_2024_full_year_donne_un_seul_segment_avec_les_dix_huit_regles(): void
    {
        $segments = $this->segmenter->segmentsForYear(2024);

        self::assertCount(1, $segments);
        $only = $segments[0];
        self::assertSame('2024-01-01', $only->start->toDateString());
        self::assertSame('2024-12-31', $only->end->toDateString());
        // 18 règles pipeline (16 historiques + R-2024-026 et R-2024-027
        // ajoutées au chantier d'audit exhaustif du 14/05/2026).
        self::assertCount(18, $only->rules);
    }

    #[Test]
    public function deux_regles_full_year_donnent_un_seul_segment(): void
    {
        $this->registry->register(self::STUB_YEAR, [
            StubFullYearRuleA::class,
            StubFullYearRuleB::class,
        ]);

        $segments = $this->segmenter->segmentsForYear(self::STUB_YEAR);

        self::assertCount(1, $segments);
        self::assertSame('2090-01-01', $segments[0]->start->toDateString());
        self::assertSame('2090-12-31', $segments[0]->end->toDateString());
        self::assertCount(2, $segments[0]->rules);
    }

    #[Test]
    public function regle_qui_apparait_au_premier_juillet_donne_deux_segments(): void
    {
        $this->registry->register(self::STUB_YEAR, [
            StubFullYearRuleA::class,
            StubAppearsJuly1Rule::class,
        ]);

        $segments = $this->segmenter->segmentsForYear(self::STUB_YEAR);

        self::assertCount(2, $segments);
        self::assertSame('2090-01-01', $segments[0]->start->toDateString());
        self::assertSame('2090-06-30', $segments[0]->end->toDateString());
        self::assertCount(1, $segments[0]->rules);
        self::assertSame('R-2090-FULL-A', $segments[0]->rules[0]->ruleCode());

        self::assertSame('2090-07-01', $segments[1]->start->toDateString());
        self::assertSame('2090-12-31', $segments[1]->end->toDateString());
        self::assertCount(2, $segments[1]->rules);
    }

    #[Test]
    public function regle_qui_disparait_au_trente_juin_donne_deux_segments(): void
    {
        $this->registry->register(self::STUB_YEAR, [
            StubFullYearRuleA::class,
            StubEndsJune30Rule::class,
        ]);

        $segments = $this->segmenter->segmentsForYear(self::STUB_YEAR);

        self::assertCount(2, $segments);
        self::assertSame('2090-01-01', $segments[0]->start->toDateString());
        self::assertSame('2090-06-30', $segments[0]->end->toDateString());
        self::assertCount(2, $segments[0]->rules);

        self::assertSame('2090-07-01', $segments[1]->start->toDateString());
        self::assertSame('2090-12-31', $segments[1]->end->toDateString());
        self::assertCount(1, $segments[1]->rules);
        self::assertSame('R-2090-FULL-A', $segments[1]->rules[0]->ruleCode());
    }

    #[Test]
    public function transition_synchronisee_au_premier_juillet_donne_deux_segments_adjacents(): void
    {
        // Règle A : 01-01 -> 06-30. Règle B : 07-01 -> 12-31. Pas de gap,
        // pas de recouvrement, pas de full-year derrière.
        $this->registry->register(self::STUB_YEAR, [
            StubEndsJune30Rule::class,
            StubAppearsJuly1Rule::class,
        ]);

        $segments = $this->segmenter->segmentsForYear(self::STUB_YEAR);

        self::assertCount(2, $segments);
        self::assertSame('2090-01-01', $segments[0]->start->toDateString());
        self::assertSame('2090-06-30', $segments[0]->end->toDateString());
        self::assertSame('R-2090-ENDS-0630', $segments[0]->rules[0]->ruleCode());
        self::assertCount(1, $segments[0]->rules);

        self::assertSame('2090-07-01', $segments[1]->start->toDateString());
        self::assertSame('2090-12-31', $segments[1]->end->toDateString());
        self::assertSame('R-2090-APPEARS-0701', $segments[1]->rules[0]->ruleCode());
        self::assertCount(1, $segments[1]->rules);
    }

    #[Test]
    public function regle_open_ended_est_clipee_a_la_fin_de_l_annee(): void
    {
        $this->registry->register(self::STUB_YEAR, [StubOpenEndedRule::class]);

        $segments = $this->segmenter->segmentsForYear(self::STUB_YEAR);

        self::assertCount(1, $segments);
        self::assertSame('2090-01-01', $segments[0]->start->toDateString());
        self::assertSame('2090-12-31', $segments[0]->end->toDateString());
    }

    #[Test]
    public function leve_pour_une_annee_non_enregistree(): void
    {
        $this->expectException(FiscalCalculationException::class);

        $this->segmenter->segmentsForYear(1900);
    }

    #[Test]
    public function cache_process_evite_le_recalcul_pour_une_meme_annee(): void
    {
        $this->registry->register(self::STUB_YEAR, [StubFullYearRuleA::class]);

        $first = $this->segmenter->segmentsForYear(self::STUB_YEAR);
        $second = $this->segmenter->segmentsForYear(self::STUB_YEAR);

        // Le cache mémorise la liste produite : on récupère exactement
        // la même référence (pas une nouvelle liste recalculée).
        self::assertSame($first, $second);
    }

    #[Test]
    public function clear_cache_force_le_recalcul_apres_mutation_du_registry(): void
    {
        // Phase 1 : peuple le cache avec 1 règle.
        $this->registry->register(self::STUB_YEAR, [StubFullYearRuleA::class]);
        $before = $this->segmenter->segmentsForYear(self::STUB_YEAR);
        self::assertCount(1, $before[0]->rules);

        // Phase 2 : muter le registry à la volée + invalider explicitement
        // le cache du segmenteur (cas typique des tests qui muted le
        // registry singleton entre deux assertions).
        $this->registry->register(self::STUB_YEAR, [
            StubFullYearRuleA::class,
            StubFullYearRuleB::class,
        ]);
        $this->segmenter->clearCache(self::STUB_YEAR);

        $after = $this->segmenter->segmentsForYear(self::STUB_YEAR);
        self::assertCount(2, $after[0]->rules);
        self::assertNotSame($before, $after, 'le cache a bien été invalidé, nouvelle liste produite');
    }

    #[Test]
    public function clear_cache_sans_year_purge_tout_le_cache(): void
    {
        // Peuple deux années dans le cache.
        $this->registry->register(2090, [StubFullYearRuleA::class]);
        $this->registry->register(2091, [StubFullYearRuleB::class]);
        $this->segmenter->segmentsForYear(2090);
        $this->segmenter->segmentsForYear(2091);

        // Mute les deux + clearCache global.
        $this->registry->register(2090, [StubFullYearRuleA::class, StubFullYearRuleB::class]);
        $this->registry->register(2091, []);
        $this->segmenter->clearCache();

        self::assertCount(2, $this->segmenter->segmentsForYear(2090)[0]->rules);
        self::assertSame([], $this->segmenter->segmentsForYear(2091));
    }
}

// ---------------------------------------------------------------------
// Stubs : règles fictives pour l'année 2090, exposant des bornes
// d'applicabilité personnalisées (sans `AnnualRuleTrait` qui forcerait
// le full-year).
// ---------------------------------------------------------------------

abstract class StubBaseRule implements FiscalRule
{
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

    public function isActive(): bool
    {
        return true;
    }

    public function pedagogicalContent(): RulePedagogicalContent
    {
        return new RulePedagogicalContent(
            tab: RuleTab::Calcul,
            section: RuleSection::Bareme,
            title: 'Test stub',
            pitch: 'Stub utilisé en tests · non rendu en UI.',
        );
    }
}

final class StubFullYearRuleA extends StubBaseRule
{
    public function ruleCode(): string
    {
        return 'R-2090-FULL-A';
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

final class StubFullYearRuleB extends StubBaseRule
{
    public function ruleCode(): string
    {
        return 'R-2090-FULL-B';
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

final class StubAppearsJuly1Rule extends StubBaseRule
{
    public function ruleCode(): string
    {
        return 'R-2090-APPEARS-0701';
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

final class StubEndsJune30Rule extends StubBaseRule
{
    public function ruleCode(): string
    {
        return 'R-2090-ENDS-0630';
    }

    public function applicabilityStart(): CarbonImmutable
    {
        return CarbonImmutable::create(2090, 1, 1);
    }

    public function applicabilityEnd(): ?CarbonImmutable
    {
        return CarbonImmutable::create(2090, 6, 30);
    }
}

final class StubOpenEndedRule extends StubBaseRule
{
    public function ruleCode(): string
    {
        return 'R-2090-OPEN-ENDED';
    }

    public function applicabilityStart(): CarbonImmutable
    {
        return CarbonImmutable::create(2090, 1, 1);
    }

    public function applicabilityEnd(): ?CarbonImmutable
    {
        return null;
    }
}
