<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\Year2025\Exemption;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\Year2025\Exemption\R2025_026_SpecificActivityExemptions;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Sanity tests pour R-2025-026 : exonérations propres à certaines
 * activités économiques (transport public personnes, agricole/forestier,
 * enseignement conduite + compétitions).
 *
 * La règle est INACTIVE par défaut (`isActive: false`) : Floty ne couvre
 * aucune entreprise utilisatrice de ces types. L'évaluation est différée
 * (mêmes raisons que R-2025-018 OIG : le PipelineContext ne porte pas
 * l'activité de l'entreprise utilisatrice en V1).
 */
final class R2025_026_SpecificActivityExemptionsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function est_inactive_par_defaut(): void
    {
        $rule = new R2025_026_SpecificActivityExemptions;

        self::assertFalse($rule->isActive());
    }

    #[Test]
    public function renvoie_toujours_not_exempt_en_v1(): void
    {
        $rule = new R2025_026_SpecificActivityExemptions;
        $context = $this->makeContext();

        $verdict = $rule->evaluate($context);

        self::assertFalse($verdict->isExempt);
    }

    #[Test]
    public function declare_les_2_taxes_concernees(): void
    {
        $rule = new R2025_026_SpecificActivityExemptions;

        self::assertSame('R-2025-026', $rule->ruleCode());
        self::assertCount(2, $rule->taxesConcerned());
    }

    #[Test]
    public function affichee_en_section_exoneration_inactive(): void
    {
        $rule = new R2025_026_SpecificActivityExemptions;
        $content = $rule->pedagogicalContent();

        self::assertSame(RuleTab::HorsPerimetre, $content->tab);
        self::assertSame(RuleSection::ExonerationInactive, $content->section);
    }

    #[Test]
    public function base_legale_couvre_les_6_articles_co2_et_polluants(): void
    {
        $rule = new R2025_026_SpecificActivityExemptions;
        $basis = $rule->legalBasis();

        $articles = array_map(fn ($entry) => $entry['article'], $basis);

        self::assertContains('L. 421-130', $articles, 'Doit citer L. 421-130 (CO₂ transport public).');
        self::assertContains('L. 421-131', $articles, 'Doit citer L. 421-131 (CO₂ agricole/forestier).');
        self::assertContains('L. 421-132', $articles, 'Doit citer L. 421-132 (CO₂ conduite + compétitions).');
        self::assertContains('L. 421-142', $articles, 'Doit citer L. 421-142 (miroir polluants transport public).');
        self::assertContains('L. 421-143', $articles, 'Doit citer L. 421-143 (miroir polluants agricole).');
        self::assertContains('L. 421-144', $articles, 'Doit citer L. 421-144 (miroir polluants conduite + compétitions).');
    }

    private function makeContext(): PipelineContext
    {
        return new PipelineContext(
            vehicle: Vehicle::factory()->create(),
            fiscalYear: 2024,
            daysInYear: 366,
        );
    }
}
