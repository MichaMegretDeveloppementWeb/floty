<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\Year2026\Pricing;

use App\Enums\Vehicle\PollutantCategory;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\Year2026\Pricing\R2026_014_PollutantsFlat;
use App\Fiscal\Year2026\Pricing\R2026_014bis_PollutantsFlat;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Goldens au centime près pour les barèmes plats polluants 2026
 * (R-2026-014 v 01/01-28/02/2026 + R-2026-014-bis v 01/03-31/12/2026).
 *
 * **Scission matérielle ADR-0022 unique du pipeline 2026** ·
 * LF 2026 art. 58 (V), IV (LOI n° 2026-103 du 19/02/2026) revalorise
 * de +30 % les tarifs polluants au 01/03/2026 ·
 *   - E (électrique / hydrogène) · 0 € (inchangé)
 *   - Catégorie 1 · 100 € → 130 € (+30 %)
 *   - Les plus polluants · 500 € → 650 € (+30 %)
 *
 * Toute régression d'une borne tarifaire entre les 2 versions sera
 * capturée. L'invariant +30 % est testé explicitement.
 */
final class R2026_PricingScalesPollutantsTest extends TestCase
{
    use RefreshDatabase;

    // ============================================================
    // R-2026-014 v 01/01-28/02/2026 · tarifs hérités 2025
    // ============================================================

    #[Test]
    public function v1_categorie_e_donne_zero(): void
    {
        self::assertSame(0.0, $this->v1Tariff(PollutantCategory::E));
    }

    #[Test]
    public function v1_categorie_1_donne_100_euros(): void
    {
        self::assertSame(100.0, $this->v1Tariff(PollutantCategory::Category1));
    }

    #[Test]
    public function v1_les_plus_polluants_donne_500_euros(): void
    {
        self::assertSame(500.0, $this->v1Tariff(PollutantCategory::MostPolluting));
    }

    // ============================================================
    // R-2026-014-bis v 01/03-31/12/2026 · revalorisation +30 % LF 2026
    // ============================================================

    #[Test]
    public function bis_categorie_e_reste_zero_inchange_vs_v1(): void
    {
        // Pas de revalorisation pour les véhicules électriques · effet
        // du barème (0 €), pas une exonération.
        self::assertSame(0.0, $this->bisTariff(PollutantCategory::E));
    }

    #[Test]
    public function bis_categorie_1_passe_a_130_euros_plus_30_pct(): void
    {
        // 100 € × 1.30 = 130 €
        self::assertSame(130.0, $this->bisTariff(PollutantCategory::Category1));
    }

    #[Test]
    public function bis_les_plus_polluants_passent_a_650_euros_plus_30_pct(): void
    {
        // 500 € × 1.30 = 650 €
        self::assertSame(650.0, $this->bisTariff(PollutantCategory::MostPolluting));
    }

    // ============================================================
    // Invariant croisé · revalorisation +30 % vs v1
    // ============================================================

    #[Test]
    public function invariant_bis_egale_v1_fois_1_30_pour_categorie_1(): void
    {
        $v1 = $this->v1Tariff(PollutantCategory::Category1);
        $bis = $this->bisTariff(PollutantCategory::Category1);

        self::assertEqualsWithDelta($v1 * 1.30, $bis, 0.01);
    }

    #[Test]
    public function invariant_bis_egale_v1_fois_1_30_pour_les_plus_polluants(): void
    {
        $v1 = $this->v1Tariff(PollutantCategory::MostPolluting);
        $bis = $this->bisTariff(PollutantCategory::MostPolluting);

        self::assertEqualsWithDelta($v1 * 1.30, $bis, 0.01);
    }

    #[Test]
    public function rule_code_v1_est_r2026_014_sans_bis(): void
    {
        $rule = new R2026_014_PollutantsFlat;
        self::assertSame('R-2026-014', $rule->ruleCode());
    }

    #[Test]
    public function rule_code_bis_est_r2026_014_bis(): void
    {
        $rule = new R2026_014bis_PollutantsFlat;
        self::assertSame('R-2026-014-bis', $rule->ruleCode());
    }

    private function v1Tariff(PollutantCategory $category): float
    {
        return $this->priceWith(new R2026_014_PollutantsFlat, $category);
    }

    private function bisTariff(PollutantCategory $category): float
    {
        return $this->priceWith(new R2026_014bis_PollutantsFlat, $category);
    }

    private function priceWith(
        R2026_014_PollutantsFlat|R2026_014bis_PollutantsFlat $rule,
        PollutantCategory $category,
    ): float {
        $vfc = VehicleFiscalCharacteristics::factory()->create();
        $context = new PipelineContext(
            vehicle: $vfc->vehicle ?? Vehicle::factory()->create(),
            fiscalYear: 2026,
            daysInYear: 365,
            currentFiscalCharacteristics: $vfc,
            resolvedPollutantCategory: $category,
        );

        return $rule->price($context)->pollutantsFullYearTariff ?? -1.0;
    }
}
