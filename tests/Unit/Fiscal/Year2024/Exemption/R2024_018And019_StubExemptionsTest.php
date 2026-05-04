<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\Year2024\Exemption;

use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\Year2024\Exemption\R2024_018_OigExemption;
use App\Fiscal\Year2024\Exemption\R2024_019_IndividualBusinessExemption;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Sanity tests pour R-2024-018 (OIG) et R-2024-019 (Entreprise
 * individuelle). Les deux règles sont **scaffolds inactifs en V1**
 * (cf. docblock de chaque règle) car {@see PipelineContext} ne porte
 * pas encore la `Company` du couple - l'évaluation est différée à V2.
 *
 * Ce test fige la sémantique « toujours notExempt en V1 » pour qu'un
 * éventuel câblage futur soit conscient de devoir mettre à jour la
 * logique ET le test simultanément.
 */
final class R2024_018And019_StubExemptionsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function r_2024_018_oig_renvoie_toujours_not_exempt_en_v1(): void
    {
        $rule = new R2024_018_OigExemption;
        $context = $this->makeContext();

        $verdict = $rule->evaluate($context);

        self::assertFalse($verdict->isExempt);
    }

    #[Test]
    public function r_2024_018_oig_declare_les_2_taxes_concernees(): void
    {
        $rule = new R2024_018_OigExemption;

        self::assertSame('R-2024-018', $rule->ruleCode());
        self::assertCount(2, $rule->taxesConcerned());
    }

    #[Test]
    public function r_2024_019_entreprise_individuelle_renvoie_toujours_not_exempt_en_v1(): void
    {
        $rule = new R2024_019_IndividualBusinessExemption;
        $context = $this->makeContext();

        $verdict = $rule->evaluate($context);

        self::assertFalse($verdict->isExempt);
    }

    #[Test]
    public function r_2024_019_entreprise_individuelle_declare_les_2_taxes_concernees(): void
    {
        $rule = new R2024_019_IndividualBusinessExemption;

        self::assertSame('R-2024-019', $rule->ruleCode());
        self::assertCount(2, $rule->taxesConcerned());
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
