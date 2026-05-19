<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\Year2026\Exemption;

use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\Year2026\Exemption\R2026_021_ShortTermRental;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre la qualification LCD 2026 (CIBS art. L. 421-129 / L. 421-141
 * stables : URL L. 421-141 = LEGIARTI000044602919 verrouillée).
 *
 * Reconduction stricte de R-2025-021. Deux conditions OR ·
 *   - durée ≤ 30 jours consécutifs
 *   - OU contrat couvre exactement un mois civil entier (1er → dernier
 *     jour du même mois).
 *
 * 2026 non bissextile : février 2026 = 28 jours.
 */
final class R2026_021_ShortTermRentalTest extends TestCase
{
    use RefreshDatabase;

    private R2026_021_ShortTermRental $rule;

    private Vehicle $vehicle;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule = new R2026_021_ShortTermRental;
        $this->vehicle = Vehicle::factory()->create();
        $this->company = Company::factory()->create();
    }

    #[Test]
    public function contrat_30_jours_qualifie_lcd(): void
    {
        $contract = $this->makeContract('2026-04-01', '2026-04-30'); // 30 j

        self::assertTrue($this->rule->isShortTermRental($contract));
    }

    #[Test]
    public function contrat_31_jours_ne_qualifie_pas_lcd_sauf_si_mois_entier(): void
    {
        $contract = $this->makeContract('2026-04-05', '2026-05-05'); // 31 j, pas mois entier

        self::assertFalse($this->rule->isShortTermRental($contract));
    }

    #[Test]
    public function contrat_couvrant_mars_2026_complet_31j_qualifie_lcd_par_mois_entier(): void
    {
        $contract = $this->makeContract('2026-03-01', '2026-03-31');

        self::assertTrue($this->rule->isShortTermRental($contract));
    }

    #[Test]
    public function contrat_couvrant_fevrier_2026_non_bissextile_28j_qualifie_lcd(): void
    {
        // Février 2026 fait 28 j (année non bissextile) : qualifie par
        // durée (< 30 j) ET par mois entier. Test redondant qui documente
        // la différence vs 2024 (29 j) et 2025 (28 j aussi).
        $contract = $this->makeContract('2026-02-01', '2026-02-28');

        self::assertTrue($this->rule->isShortTermRental($contract));
    }

    #[Test]
    public function contrat_couvrant_janvier_2026_complet_31j_qualifie_lcd(): void
    {
        $contract = $this->makeContract('2026-01-01', '2026-01-31');

        self::assertTrue($this->rule->isShortTermRental($contract));
    }

    #[Test]
    public function contrat_qui_chevauche_2_mois_meme_si_31j_ne_qualifie_pas_lcd(): void
    {
        $contract = $this->makeContract('2026-03-15', '2026-04-14'); // 31 j sur 2 mois

        self::assertFalse($this->rule->isShortTermRental($contract));
    }

    #[Test]
    public function contrat_15_jours_qualifie_lcd(): void
    {
        $contract = $this->makeContract('2026-06-10', '2026-06-24');

        self::assertTrue($this->rule->isShortTermRental($contract));
    }

    #[Test]
    public function contrat_60_jours_ne_qualifie_pas_lcd(): void
    {
        $contract = $this->makeContract('2026-05-01', '2026-06-29'); // 60 j

        self::assertFalse($this->rule->isShortTermRental($contract));
    }

    #[Test]
    public function evaluate_remonte_les_jours_lcd_dans_le_couple(): void
    {
        $lcd = $this->makeContract('2026-04-01', '2026-04-15'); // 15 j
        $lld = $this->makeContract('2026-05-01', '2026-08-31'); // > 30 j

        $verdict = $this->rule->evaluate($this->makeContext([$lcd, $lld]));

        self::assertTrue($verdict->isExempt);
        self::assertSame(15, $verdict->exemptDaysCount);
    }

    #[Test]
    public function evaluate_aucun_lcd_dans_le_couple_renvoie_not_exempt(): void
    {
        $lld = $this->makeContract('2026-05-01', '2026-08-31');

        $verdict = $this->rule->evaluate($this->makeContext([$lld]));

        self::assertFalse($verdict->isExempt);
    }

    #[Test]
    public function evaluate_mois_civil_entier_compte_tous_les_jours_du_mois(): void
    {
        $marsEntier = $this->makeContract('2026-03-01', '2026-03-31');

        $verdict = $this->rule->evaluate($this->makeContext([$marsEntier]));

        self::assertTrue($verdict->isExempt);
        self::assertSame(31, $verdict->exemptDaysCount);
    }

    private function makeContract(string $start, string $end): Contract
    {
        return Contract::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'company_id' => $this->company->id,
            'start_date' => $start,
            'end_date' => $end,
        ]);
    }

    /**
     * @param  list<Contract>  $contracts
     */
    private function makeContext(array $contracts): PipelineContext
    {
        return new PipelineContext(
            vehicle: $this->vehicle,
            fiscalYear: 2026,
            daysInYear: 365,
            contractsForPair: $contracts,
        );
    }
}
