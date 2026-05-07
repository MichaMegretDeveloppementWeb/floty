<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Fiscal\RiskDetection;

use App\Fiscal\Year2024\Exemption\R2024_021_ShortTermRental;
use App\Models\Contract;
use App\Services\Fiscal\RiskDetection\LcdContractFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre le filtre LCD du moteur de détection (Phase 11 D2).
 *
 * Le service délègue strictement à `R2024_021_ShortTermRental` ; les
 * cas de bord de la qualification LCD sont déjà testés par
 * `R2024_021_ShortTermRentalTest`. Ici on vérifie que la délégation
 * est correcte sur les bornes typiques (30 j inclus, 31 j exclu sauf
 * mois entier, mois civil entier inclus).
 */
final class LcdContractFilterTest extends TestCase
{
    use RefreshDatabase;

    private LcdContractFilter $filter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filter = new LcdContractFilter(new R2024_021_ShortTermRental);
    }

    #[Test]
    public function garde_un_contrat_30_jours(): void
    {
        $contract = $this->makeContract('2025-04-01', '2025-04-30'); // 30 j

        self::assertTrue($this->filter->isLcd($contract));
    }

    #[Test]
    public function garde_un_contrat_mois_civil_complet(): void
    {
        $contract = $this->makeContract('2025-03-01', '2025-03-31'); // 31 j mais mois civil

        self::assertTrue($this->filter->isLcd($contract));
    }

    #[Test]
    public function exclut_un_contrat_31_jours_non_mois_civil(): void
    {
        $contract = $this->makeContract('2025-03-15', '2025-04-14'); // 31 j à cheval

        self::assertFalse($this->filter->isLcd($contract));
    }

    #[Test]
    public function exclut_un_contrat_long(): void
    {
        $contract = $this->makeContract('2025-01-01', '2025-06-30');

        self::assertFalse($this->filter->isLcd($contract));
    }

    #[Test]
    public function reproduit_le_verdict_de_la_regle_r2024_021_pour_chaque_contrat(): void
    {
        // Sentinelle minimale : la règle étant `final readonly` (non
        // mockable), on vérifie la délégation par cohérence stricte
        // entre l'output du filter et l'output direct de la règle.
        $rule = new R2024_021_ShortTermRental;
        $contracts = [
            $this->makeContract('2025-01-01', '2025-01-15'),
            $this->makeContract('2025-03-01', '2025-03-31'),
            $this->makeContract('2025-05-01', '2025-08-31'),
        ];

        foreach ($contracts as $contract) {
            self::assertSame(
                $rule->isShortTermRental($contract),
                $this->filter->isLcd($contract),
                sprintf('Verdict diverge pour le contrat %s → %s', $contract->start_date, $contract->end_date),
            );
        }
    }

    private function makeContract(string $start, string $end): Contract
    {
        return Contract::factory()->create([
            'start_date' => $start,
            'end_date' => $end,
            'contract_type' => Contract::deriveTypeFromDates($start, $end),
        ]);
    }
}
