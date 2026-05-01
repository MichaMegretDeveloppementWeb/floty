<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\Year2024\Exemption;

use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\Year2024\Exemption\R2024_015_HandicapAccess;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre l'exonération handicap (CIBS L. 421-123 / L. 421-136).
 *
 * Spécificité : exonération **totale** des deux taxes ET les tarifs
 * annuels pleins sont zéroés dans le breakdown (pas d'affichage « ce
 * que vous auriez payé »). C'est la seule règle qui utilise le
 * `fullZeroingTariffs()` factory de l'ExemptionVerdict.
 *
 * Le flag `handicap_access` est ce qui fait foi — la carrosserie HB
 * (ou autre) ne suffit pas à elle seule.
 */
final class R2024_015_HandicapAccessTest extends TestCase
{
    use RefreshDatabase;

    private R2024_015_HandicapAccess $rule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule = new R2024_015_HandicapAccess;
    }

    #[Test]
    public function vehicule_avec_flag_handicap_donne_exoneration_totale_avec_zeroing(): void
    {
        $vfc = $this->makeVfc(['handicap_access' => true]);

        $verdict = $this->rule->evaluate($this->makeContext($vfc));

        self::assertTrue($verdict->isExempt);
        self::assertTrue($verdict->zeroesFullYearTariffs);
        self::assertStringContainsString('handicap', (string) $verdict->reason);
    }

    #[Test]
    public function vehicule_sans_flag_handicap_n_a_pas_d_exoneration(): void
    {
        $vfc = $this->makeVfc(['handicap_access' => false]);

        $verdict = $this->rule->evaluate($this->makeContext($vfc));

        self::assertFalse($verdict->isExempt);
        self::assertFalse($verdict->zeroesFullYearTariffs);
    }

    #[Test]
    public function contexte_sans_vfc_n_a_pas_d_exoneration(): void
    {
        // Cas race : pipeline appelé avant la classification VFC.
        $context = new PipelineContext(
            vehicle: Vehicle::factory()->create(),
            fiscalYear: 2024,
            daysInYear: 366,
            currentFiscalCharacteristics: null,
        );

        $verdict = $this->rule->evaluate($context);

        self::assertFalse($verdict->isExempt);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeVfc(array $overrides): VehicleFiscalCharacteristics
    {
        return VehicleFiscalCharacteristics::factory()->create($overrides);
    }

    private function makeContext(VehicleFiscalCharacteristics $vfc): PipelineContext
    {
        return new PipelineContext(
            vehicle: $vfc->vehicle ?? Vehicle::factory()->create(),
            fiscalYear: 2024,
            daysInYear: 366,
            currentFiscalCharacteristics: $vfc,
        );
    }
}
