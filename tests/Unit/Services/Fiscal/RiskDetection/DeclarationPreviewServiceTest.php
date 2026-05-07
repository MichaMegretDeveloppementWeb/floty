<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Fiscal\RiskDetection;

use App\Enums\Contract\ContractType;
use App\Enums\FiscalReviewDecision\ReviewDecisionType;
use App\Models\Company;
use App\Models\Contract;
use App\Models\FiscalDeclaration;
use App\Models\FiscalReviewDecision;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Fiscal\RiskDetection\DeclarationPreviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Couvre l'orchestration preview (Phase 11 D3) :
 *   - intégration RiskDetection (D2)
 *   - pré-application des décisions persistées par fingerprint
 *   - lookup déclaration active
 *   - comptage des pending
 */
final class DeclarationPreviewServiceTest extends TestCase
{
    use RefreshDatabase;

    private DeclarationPreviewService $service;

    private Company $company;

    private Vehicle $vehicle;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(DeclarationPreviewService::class);
        $this->company = Company::factory()->create();
        $this->vehicle = Vehicle::factory()->create();
        $this->user = User::factory()->create();
    }

    #[Test]
    public function leve_si_company_inexistante(): void
    {
        $this->expectException(RuntimeException::class);

        $this->service->preview(99999, 2025);
    }

    #[Test]
    public function preview_vide_si_aucun_contrat(): void
    {
        $preview = $this->service->preview($this->company->id, 2025);

        self::assertSame([], $preview->clusters);
        self::assertSame(0, $preview->pendingClustersCount);
        self::assertTrue($preview->canGenerate);
        self::assertNull($preview->declaration);
    }

    #[Test]
    public function pre_applique_la_decision_persistee_par_fingerprint(): void
    {
        // Crée un cluster détectable (cumul > 30 j, intervalle ≤ 15)
        $this->makeChainOfTwo();

        // Première preview pour récupérer le fingerprint
        $clusters = $this->service->preview($this->company->id, 2025)->clusters;
        self::assertCount(1, $clusters);
        $fp = $clusters[0]->fingerprint;

        // Persiste une décision pour ce fingerprint
        FiscalReviewDecision::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->withFingerprint($fp)
            ->conserved('Justification cluster moyen')
            ->create(['decided_by' => $this->user->id]);

        // Re-preview : la décision doit être pré-appliquée
        $preview = $this->service->preview($this->company->id, 2025);

        self::assertCount(1, $preview->clusters);
        self::assertSame(ReviewDecisionType::Conserved, $preview->clusters[0]->decision);
        self::assertSame('Justification cluster moyen', $preview->clusters[0]->justification);
        self::assertSame(0, $preview->pendingClustersCount);
        self::assertTrue($preview->canGenerate);
    }

    #[Test]
    public function ignore_une_decision_au_fingerprint_different(): void
    {
        $this->makeChainOfTwo();

        FiscalReviewDecision::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->withFingerprint(str_repeat('a', 64))
            ->conserved()
            ->create(['decided_by' => $this->user->id]);

        $preview = $this->service->preview($this->company->id, 2025);

        self::assertCount(1, $preview->clusters);
        self::assertNull($preview->clusters[0]->decision);
        self::assertSame(1, $preview->pendingClustersCount);
        self::assertFalse($preview->canGenerate);
    }

    #[Test]
    public function expose_la_declaration_active_si_existante(): void
    {
        FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->draft()
            ->create();

        $preview = $this->service->preview($this->company->id, 2025);

        self::assertNotNull($preview->declaration);
        self::assertSame($this->company->id, $preview->declaration->companyId);
        self::assertSame(2025, $preview->declaration->fiscalYear);
    }

    #[Test]
    public function ignore_une_declaration_obsolete_pour_l_active(): void
    {
        FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->obsolete()
            ->create();

        $preview = $this->service->preview($this->company->id, 2025);

        self::assertNull($preview->declaration);
    }

    #[Test]
    public function compte_les_pending_correctement(): void
    {
        // 2 clusters : 1 décidé, 1 pending
        $this->makeChainOfTwo();

        // Cluster 2 : nouvelle paire LCD chaînée sur un autre véhicule
        $vehicle2 = Vehicle::factory()->create();
        Contract::factory()->create([
            'company_id' => $this->company->id,
            'vehicle_id' => $vehicle2->id,
            'start_date' => '2025-06-01',
            'end_date' => '2025-06-20',
            'contract_type' => ContractType::Lcd,
        ]);
        Contract::factory()->create([
            'company_id' => $this->company->id,
            'vehicle_id' => $vehicle2->id,
            'start_date' => '2025-06-26',
            'end_date' => '2025-07-15',
            'contract_type' => ContractType::Lcd,
        ]);

        $clusters = $this->service->preview($this->company->id, 2025)->clusters;
        self::assertCount(2, $clusters);

        // Persiste UNIQUEMENT la décision du 1er cluster
        FiscalReviewDecision::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->withFingerprint($clusters[0]->fingerprint)
            ->requalified()
            ->create(['decided_by' => $this->user->id]);

        $preview = $this->service->preview($this->company->id, 2025);

        self::assertSame(1, $preview->pendingClustersCount);
        self::assertFalse($preview->canGenerate);
    }

    private function makeChainOfTwo(): void
    {
        Contract::factory()->create([
            'company_id' => $this->company->id,
            'vehicle_id' => $this->vehicle->id,
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-20',
            'contract_type' => ContractType::Lcd,
        ]);
        Contract::factory()->create([
            'company_id' => $this->company->id,
            'vehicle_id' => $this->vehicle->id,
            'start_date' => '2025-01-26',
            'end_date' => '2025-02-14',
            'contract_type' => ContractType::Lcd,
        ]);
    }
}
