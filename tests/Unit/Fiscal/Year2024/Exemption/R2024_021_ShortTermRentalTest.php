<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\Year2024\Exemption;

use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\Year2024\Exemption\R2024_021_ShortTermRental;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre la qualification LCD (Location de Courte Durée) - CIBS art.
 * L. 421-129 / L. 421-141, BOFiP § 180-190.
 *
 * Deux conditions OR :
 *   - durée ≤ 30 jours consécutifs
 *   - OU contrat couvre exactement un mois civil entier
 *     (1er → dernier jour du même mois)
 *
 * Bordures clés testées : 30j, 31j, mois entier (févier 2024 = 29j),
 * mois entier (mars 2024 = 31j), faux mois entier (chevauche 2 mois).
 */
final class R2024_021_ShortTermRentalTest extends TestCase
{
    use RefreshDatabase;

    private R2024_021_ShortTermRental $rule;

    private Vehicle $vehicle;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule = new R2024_021_ShortTermRental;
        $this->vehicle = Vehicle::factory()->create();
        $this->company = Company::factory()->create();
    }

    #[Test]
    public function contrat_30_jours_qualifie_lcd(): void
    {
        $contract = $this->makeContract('2024-04-01', '2024-04-30'); // 30 j

        self::assertTrue($this->rule->isShortTermRental($contract));
    }

    #[Test]
    public function contrat_31_jours_ne_qualifie_pas_lcd_sauf_si_mois_entier(): void
    {
        // 31 jours mais commence le 5 → pas un mois civil entier
        $contract = $this->makeContract('2024-04-05', '2024-05-05'); // 31 j

        self::assertFalse($this->rule->isShortTermRental($contract));
    }

    #[Test]
    public function contrat_couvrant_mars_2024_complet_31j_qualifie_lcd_par_mois_entier(): void
    {
        $contract = $this->makeContract('2024-03-01', '2024-03-31'); // 31 j MAIS mois entier

        self::assertTrue($this->rule->isShortTermRental($contract));
    }

    #[Test]
    public function contrat_couvrant_fevrier_2024_bissextile_29j_qualifie_lcd(): void
    {
        // Février 2024 fait 29 j (année bissextile) - < 30 j, qualifie
        // déjà par durée. Test redondant mais documente la bordure.
        $contract = $this->makeContract('2024-02-01', '2024-02-29');

        self::assertTrue($this->rule->isShortTermRental($contract));
    }

    #[Test]
    public function contrat_couvrant_janvier_2024_complet_31j_qualifie_lcd(): void
    {
        $contract = $this->makeContract('2024-01-01', '2024-01-31');

        self::assertTrue($this->rule->isShortTermRental($contract));
    }

    #[Test]
    public function contrat_qui_chevauche_2_mois_meme_si_31j_ne_qualifie_pas_lcd(): void
    {
        // Du 15/03 au 14/04 = 31 j mais sur 2 mois → pas LCD
        $contract = $this->makeContract('2024-03-15', '2024-04-14');

        self::assertFalse($this->rule->isShortTermRental($contract));
    }

    #[Test]
    public function contrat_15_jours_qualifie_lcd(): void
    {
        $contract = $this->makeContract('2024-06-10', '2024-06-24'); // 15 j

        self::assertTrue($this->rule->isShortTermRental($contract));
    }

    #[Test]
    public function contrat_60_jours_ne_qualifie_pas_lcd(): void
    {
        $contract = $this->makeContract('2024-05-01', '2024-06-29'); // 60 j

        self::assertFalse($this->rule->isShortTermRental($contract));
    }

    #[Test]
    public function evaluate_remonte_les_jours_lcd_dans_le_couple(): void
    {
        $lcd = $this->makeContract('2024-04-01', '2024-04-15'); // 15 j → LCD
        $lld = $this->makeContract('2024-05-01', '2024-08-31'); // > 30 j

        $verdict = $this->rule->evaluate($this->makeContext([$lcd, $lld]));

        self::assertTrue($verdict->isExempt);
        self::assertSame(15, $verdict->exemptDaysCount);
    }

    #[Test]
    public function evaluate_aucun_lcd_dans_le_couple_renvoie_not_exempt(): void
    {
        $lld = $this->makeContract('2024-05-01', '2024-08-31');

        $verdict = $this->rule->evaluate($this->makeContext([$lld]));

        self::assertFalse($verdict->isExempt);
    }

    #[Test]
    public function evaluate_mois_civil_entier_compte_tous_les_jours_du_mois(): void
    {
        $marchEntier = $this->makeContract('2024-03-01', '2024-03-31'); // 31 j → LCD via mois entier

        $verdict = $this->rule->evaluate($this->makeContext([$marchEntier]));

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
            fiscalYear: 2024,
            daysInYear: 366,
            contractsForPair: $contracts,
        );
    }
}
