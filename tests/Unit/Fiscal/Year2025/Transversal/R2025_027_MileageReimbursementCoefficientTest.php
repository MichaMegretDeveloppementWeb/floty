<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\Year2025\Transversal;

use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\Year2025\Transversal\R2025_027_MileageReimbursementCoefficient;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Sanity tests pour R-2025-027 : coefficient pondérateur et minoration
 * des frais kilométriques (CIBS L. 421-109, L. 421-110, L. 421-111).
 *
 * La règle est INACTIVE par défaut (`isActive: false`) : Floty ne couvre
 * pas les véhicules personnels de salariés/dirigeants avec frais
 * kilométriques pris en charge. La méthode `apply()` retourne le
 * contexte inchangé.
 */
final class R2025_027_MileageReimbursementCoefficientTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function est_inactive_par_defaut(): void
    {
        $rule = new R2025_027_MileageReimbursementCoefficient;

        self::assertFalse($rule->isActive());
    }

    #[Test]
    public function apply_retourne_le_contexte_inchange(): void
    {
        $rule = new R2025_027_MileageReimbursementCoefficient;
        $context = $this->makeContext();

        $result = $rule->apply($context);

        self::assertSame($context, $result);
    }

    #[Test]
    public function declare_les_2_taxes_concernees(): void
    {
        $rule = new R2025_027_MileageReimbursementCoefficient;

        self::assertSame('R-2025-027', $rule->ruleCode());
        self::assertCount(2, $rule->taxesConcerned());
    }

    #[Test]
    public function affichee_en_section_exoneration_inactive(): void
    {
        $rule = new R2025_027_MileageReimbursementCoefficient;
        $content = $rule->pedagogicalContent();

        self::assertSame(RuleTab::HorsPerimetre, $content->tab);
        self::assertSame(RuleSection::ExonerationInactive, $content->section);
    }

    #[Test]
    public function base_legale_cite_les_3_articles_du_sous_paragraphe(): void
    {
        $rule = new R2025_027_MileageReimbursementCoefficient;
        $basis = $rule->legalBasis();

        $articles = array_map(fn ($entry) => $entry['article'], $basis);

        self::assertContains('L. 421-109', $articles, 'Doit citer L. 421-109 (chapeau sous-paragraphe).');
        self::assertContains('L. 421-110', $articles, 'Doit citer L. 421-110 (coefficient pondérateur).');
        self::assertContains('L. 421-111', $articles, 'Doit citer L. 421-111 (minoration 15 000 €).');
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
