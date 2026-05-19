<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\Year2025\Transversal;

use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\Year2025\Transversal\R2025_003_FinalRounding;
use App\Models\Vehicle;
use App\Services\Fiscal\FleetFiscalAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * R-2025-003 : Arrondi half-up commercial (règle marqueur, reconduction
 * stricte R-2024-003).
 *
 * Sémantique BOFiP : « le montant total à payer par chaque redevable
 * est arrondi à l'euro le plus proche, sans arrondi intermédiaire ».
 * L'arrondi par redevable s'opère dans
 * {@see FleetFiscalAggregator::companyAnnualTax()}.
 *
 * Cette classe est conservée comme marqueur dans le pipeline :
 * `apply()` n'altère pas les montants, ajoute juste le rule code dans
 * la trace `appliedRuleCodes`.
 */
final class R2025_003_FinalRoundingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function apply_ajoute_le_rule_code_dans_applied_rules(): void
    {
        $context = $this->makeContext(co2Due: 173.456, pollutantsDue: 100.0);
        $result = (new R2025_003_FinalRounding)->apply($context);

        self::assertContains('R-2025-003', $result->appliedRuleCodes);
    }

    #[Test]
    public function apply_n_altere_pas_les_montants_de_taxe(): void
    {
        // Sémantique marker : les montants intermédiaires ne sont pas
        // arrondis ici. L'arrondi par redevable se fait dans
        // FleetFiscalAggregator (cf. PHPDoc).
        $context = $this->makeContext(co2Due: 173.4567, pollutantsDue: 99.999);
        $result = (new R2025_003_FinalRounding)->apply($context);

        self::assertSame(173.4567, $result->co2Due);
        self::assertSame(99.999, $result->pollutantsDue);
    }

    #[Test]
    public function apply_est_idempotent_sur_les_montants(): void
    {
        // 2 passes consécutives doivent produire le même résultat
        // numérique (la trace appliedRuleCodes contiendra 2 occurrences,
        // ce qui est normal puisque c'est un journal).
        $context = $this->makeContext(co2Due: 173.0, pollutantsDue: 100.0);
        $rule = new R2025_003_FinalRounding;
        $r1 = $rule->apply($context);
        $r2 = $rule->apply($r1);

        self::assertSame(173.0, $r2->co2Due);
        self::assertSame(100.0, $r2->pollutantsDue);
    }

    private function makeContext(float $co2Due, float $pollutantsDue): PipelineContext
    {
        return new PipelineContext(
            vehicle: Vehicle::factory()->create(),
            fiscalYear: 2025,
            daysInYear: 365,
            co2Due: $co2Due,
            pollutantsDue: $pollutantsDue,
        );
    }
}
