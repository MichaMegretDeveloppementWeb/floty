<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Fiscal\Declaration;

use App\Enums\Contract\ContractType;
use App\Models\Company;
use App\Models\Contract;
use App\Models\FiscalDeclaration;
use App\Models\Vehicle;
use App\Services\Fiscal\Declaration\PendingDeclarationsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre la résolution des déclarations en attente (Phase 11 D4) :
 *   - années avec contrats sans déclaration `generated` → pending
 *   - année en cours exclue (pas encore due)
 *   - déclaration `draft` ou `deferred` ne suffit pas (= encore pending)
 *   - déclaration `generated` active = year retiré
 *   - déclaration `generated` mais obsolète = year toujours pending
 *   - tri plus ancienne en premier
 *   - deadline 30/04/N+1 + isOverdue
 */
final class PendingDeclarationsResolverTest extends TestCase
{
    use RefreshDatabase;

    private PendingDeclarationsResolver $resolver;

    private Company $company;

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = $this->app->make(PendingDeclarationsResolver::class);
        $this->company = Company::factory()->create();
        $this->vehicle = Vehicle::factory()->create();
    }

    #[Test]
    public function liste_vide_si_aucun_contrat(): void
    {
        Carbon::setTestNow('2026-06-01');

        self::assertSame([], $this->resolver->pendingForCompany($this->company->id));

        Carbon::setTestNow();
    }

    #[Test]
    public function annee_passee_avec_contrat_sans_declaration_apparait_en_pending(): void
    {
        Carbon::setTestNow('2026-06-01');
        $this->makeContract('2024-03-01', '2024-04-15');
        $this->makeContract('2025-06-01', '2025-06-30');

        $pending = $this->resolver->pendingForCompany($this->company->id);

        self::assertCount(2, $pending);
        self::assertSame(2024, $pending[0]->fiscalYear);
        self::assertSame(2025, $pending[1]->fiscalYear);

        Carbon::setTestNow();
    }

    #[Test]
    public function annee_en_cours_n_est_pas_dans_la_liste_pending(): void
    {
        Carbon::setTestNow('2026-06-01');
        $this->makeContract('2026-03-01', '2026-04-15');

        self::assertSame([], $this->resolver->pendingForCompany($this->company->id));

        Carbon::setTestNow();
    }

    #[Test]
    public function declaration_generated_active_retire_l_annee(): void
    {
        Carbon::setTestNow('2026-06-01');
        $this->makeContract('2025-03-01', '2025-04-15');
        FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->generated()
            ->create();

        self::assertSame([], $this->resolver->pendingForCompany($this->company->id));

        Carbon::setTestNow();
    }

    #[Test]
    public function declaration_obsolete_garde_l_annee_en_pending(): void
    {
        Carbon::setTestNow('2026-06-01');
        $this->makeContract('2025-03-01', '2025-04-15');
        FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->obsolete()
            ->create();

        $pending = $this->resolver->pendingForCompany($this->company->id);

        self::assertCount(1, $pending);
        self::assertSame(2025, $pending[0]->fiscalYear);

        Carbon::setTestNow();
    }

    #[Test]
    public function declaration_draft_ou_deferred_garde_l_annee_en_pending(): void
    {
        Carbon::setTestNow('2026-06-01');
        $this->makeContract('2025-03-01', '2025-04-15');
        FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->deferred()
            ->create();

        $pending = $this->resolver->pendingForCompany($this->company->id);

        self::assertCount(1, $pending);
        self::assertSame(2025, $pending[0]->fiscalYear);

        Carbon::setTestNow();
    }

    #[Test]
    public function tri_plus_ancienne_annee_en_premier(): void
    {
        Carbon::setTestNow('2026-06-01');
        $this->makeContract('2025-03-01', '2025-03-15');
        $this->makeContract('2023-06-01', '2023-06-30');
        $this->makeContract('2024-09-01', '2024-09-15');

        $pending = $this->resolver->pendingForCompany($this->company->id);

        self::assertSame([2023, 2024, 2025], array_map(fn ($p) => $p->fiscalYear, $pending));

        Carbon::setTestNow();
    }

    #[Test]
    public function deadline_calculee_au_30_avril_n_plus_1(): void
    {
        Carbon::setTestNow('2026-03-15');
        $this->makeContract('2025-06-01', '2025-06-30');

        $pending = $this->resolver->pendingForCompany($this->company->id);

        self::assertSame('2026-04-30', $pending[0]->deadline);
        self::assertFalse($pending[0]->isOverdue);

        Carbon::setTestNow();
    }

    #[Test]
    public function is_overdue_si_now_apres_30_avril_n_plus_1(): void
    {
        Carbon::setTestNow('2026-05-15');
        $this->makeContract('2025-06-01', '2025-06-30');

        $pending = $this->resolver->pendingForCompany($this->company->id);

        self::assertSame('2026-04-30', $pending[0]->deadline);
        self::assertTrue($pending[0]->isOverdue);

        Carbon::setTestNow();
    }

    private function makeContract(string $start, string $end): Contract
    {
        return Contract::factory()->create([
            'company_id' => $this->company->id,
            'vehicle_id' => $this->vehicle->id,
            'start_date' => $start,
            'end_date' => $end,
            'contract_type' => Contract::deriveTypeFromDates($start, $end) ?? ContractType::Lcd,
        ]);
    }
}
