<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\Year2025\Exemption;

use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Fiscal\Contracts\ExemptionRule;
use App\Fiscal\Contracts\LcdQualifier;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\Year2025\Exemption\R2025_021_ShortTermRental;
use App\Fiscal\Year2025\Exemption\R2025_021_WithOptOuts;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre le decorator {@see R2025_021_WithOptOuts} (Phase 11 D5.1).
 *
 * Sémantique :
 *   - sans opt-outs : strictement identique à la règle wrappée
 *   - avec opt-outs : `isShortTermRental()` retourne `false` pour les
 *     contrats opt-out, sinon delegate
 *   - `evaluate()` ré-implémente la logique avec la version filtrée
 *   - métadonnées (name, description, etc.) déléguées intactes
 *   - `ruleCode` reste `R-2025-021` (même règle légale)
 */
final class R2025_021_WithOptOutsTest extends TestCase
{
    use RefreshDatabase;

    private R2025_021_ShortTermRental $wrapped;

    private Vehicle $vehicle;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wrapped = new R2025_021_ShortTermRental;
        $this->vehicle = Vehicle::factory()->create();
        $this->company = Company::factory()->create();
    }

    #[Test]
    public function sans_opt_outs_le_decorator_a_le_meme_comportement_que_la_regle_wrappee(): void
    {
        $decorator = new R2025_021_WithOptOuts($this->wrapped, []);

        $lcd = $this->makeContract('2024-04-01', '2024-04-15');     // 15 j → LCD
        $lld = $this->makeContract('2024-05-01', '2024-08-31');     // 4 mois → non-LCD
        $monthly = $this->makeContract('2024-03-01', '2024-03-31'); // mois civil → LCD

        self::assertSame($this->wrapped->isShortTermRental($lcd), $decorator->isShortTermRental($lcd));
        self::assertSame($this->wrapped->isShortTermRental($lld), $decorator->isShortTermRental($lld));
        self::assertSame($this->wrapped->isShortTermRental($monthly), $decorator->isShortTermRental($monthly));
    }

    #[Test]
    public function avec_opt_outs_les_contrats_dans_la_liste_ne_sont_plus_lcd(): void
    {
        $lcd1 = $this->makeContract('2024-04-01', '2024-04-15'); // 15 j LCD
        $lcd2 = $this->makeContract('2024-05-01', '2024-05-30'); // 30 j LCD

        $decorator = new R2025_021_WithOptOuts($this->wrapped, [$lcd1->id]);

        self::assertFalse($decorator->isShortTermRental($lcd1), 'lcd1 est opt-out, doit être non-LCD');
        self::assertTrue($decorator->isShortTermRental($lcd2), "lcd2 n'est pas opt-out, reste LCD");
    }

    #[Test]
    public function evaluate_sans_opt_outs_produit_le_meme_verdict_que_la_regle_wrappee(): void
    {
        $lcd = $this->makeContract('2024-04-01', '2024-04-15'); // 15 j → 15 jours exempts
        $lld = $this->makeContract('2024-05-01', '2024-08-31'); // non-LCD, ne compte pas

        $context = $this->makeContext([$lcd, $lld]);

        $wrappedVerdict = $this->wrapped->evaluate($context);
        $decoratorVerdict = (new R2025_021_WithOptOuts($this->wrapped, []))->evaluate($context);

        self::assertSame($wrappedVerdict->isExempt, $decoratorVerdict->isExempt);
        self::assertSame($wrappedVerdict->exemptDaysCount, $decoratorVerdict->exemptDaysCount);
        self::assertSame($wrappedVerdict->ruleCode, $decoratorVerdict->ruleCode);
    }

    #[Test]
    public function evaluate_avec_opt_outs_exclut_les_contrats_de_la_liste_du_compte_des_jours_exempts(): void
    {
        $lcd1 = $this->makeContract('2024-04-01', '2024-04-15'); // 15 j (opt-out)
        $lcd2 = $this->makeContract('2024-05-01', '2024-05-30'); // 30 j (reste LCD)

        $context = $this->makeContext([$lcd1, $lcd2]);

        $decorator = new R2025_021_WithOptOuts($this->wrapped, [$lcd1->id]);
        $verdict = $decorator->evaluate($context);

        self::assertTrue($verdict->isExempt);
        self::assertSame(30, $verdict->exemptDaysCount, 'seul lcd2 (30 j) compte ; lcd1 est opt-out');
    }

    #[Test]
    public function evaluate_avec_tous_les_lcd_opt_out_renvoie_not_exempt(): void
    {
        $lcd = $this->makeContract('2024-04-01', '2024-04-15');

        $decorator = new R2025_021_WithOptOuts($this->wrapped, [$lcd->id]);
        $verdict = $decorator->evaluate($this->makeContext([$lcd]));

        self::assertFalse($verdict->isExempt);
        self::assertNull($verdict->exemptDaysCount);
    }

    #[Test]
    public function metadonnees_deleguees_a_la_regle_wrappee(): void
    {
        $decorator = new R2025_021_WithOptOuts($this->wrapped, [42]);

        self::assertSame('R-2025-021', $decorator->ruleCode());
        self::assertSame($this->wrapped->name(), $decorator->name());
        self::assertSame($this->wrapped->description(), $decorator->description());
        self::assertSame(RuleType::Exemption, $decorator->ruleType());
        self::assertSame($this->wrapped->displayOrder(), $decorator->displayOrder());
        self::assertSame($this->wrapped->legalBasis(), $decorator->legalBasis());
        self::assertSame($this->wrapped->isActive(), $decorator->isActive());
        self::assertSame([TaxType::Co2, TaxType::Pollutants], $decorator->taxesConcerned());
        self::assertEquals(
            $this->wrapped->applicabilityStart()->toDateString(),
            $decorator->applicabilityStart()->toDateString(),
        );
        self::assertEquals(
            $this->wrapped->applicabilityEnd()?->toDateString(),
            $decorator->applicabilityEnd()?->toDateString(),
        );
    }

    #[Test]
    public function le_decorator_est_un_lcd_qualifier(): void
    {
        $decorator = new R2025_021_WithOptOuts($this->wrapped, []);
        self::assertInstanceOf(LcdQualifier::class, $decorator);
        self::assertInstanceOf(ExemptionRule::class, $decorator);
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
