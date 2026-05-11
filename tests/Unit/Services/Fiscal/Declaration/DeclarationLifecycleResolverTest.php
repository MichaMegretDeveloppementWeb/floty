<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Fiscal\Declaration;

use App\Enums\Contract\ContractType;
use App\Enums\FiscalDeclaration\DeclarationLifecycleState;
use App\Enums\FiscalDeclaration\InvalidationReasonType;
use App\Models\Company;
use App\Models\Contract;
use App\Models\FiscalDeclaration;
use App\Models\FiscalReviewDecision;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Fiscal\Declaration\DeclarationLifecycleResolver;
use App\Services\Fiscal\RiskDetection\RiskDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre {@see DeclarationLifecycleResolver} (Phase 11 D5.8) en
 * vérifiant la dérivation de l'état S1..S7 depuis la combinaison
 * `status × is_obsolete × supersededBy × predecessor` + la composition
 * de l'historique de chaîne, des motifs d'obsolescence et du compteur
 * de clusters pending.
 *
 * Pattern d'isolation cohérent avec `DeclarationPreviewServiceTest` :
 * les services métier composés (`RiskDetectionService`, repo decisions)
 * sont `final readonly`, donc on utilise des fixtures BDD réelles plutôt
 * que des mocks. Les chaînes LCD sont matérialisées via 2 contrats
 * consécutifs sur un même véhicule (cumul = 40j > seuils par défaut).
 */
final class DeclarationLifecycleResolverTest extends TestCase
{
    use RefreshDatabase;

    private const YEAR = 2024;

    private DeclarationLifecycleResolver $resolver;

    private Company $company;

    private Vehicle $vehicle;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = $this->app->make(DeclarationLifecycleResolver::class);
        $this->company = Company::factory()->create([
            'short_code' => 'ACM',
            'legal_name' => 'ACM SARL',
        ]);
        $this->vehicle = Vehicle::factory()->create();
        $this->user = User::factory()->create();
    }

    #[Test]
    public function s1_untouched_si_aucune_declaration(): void
    {
        $state = $this->resolver->resolveForCompanyYear($this->company->id, self::YEAR);

        self::assertSame(DeclarationLifecycleState::Untouched, $state->state);
        self::assertNull($state->currentDeclaration);
        self::assertNull($state->predecessorDeclaration);
        self::assertSame(0, $state->pendingClustersCount);
        self::assertFalse($state->canGenerate);
        self::assertSame([], $state->obsoleteReasons);
        self::assertSame([], $state->historyChain);
    }

    #[Test]
    public function s2_draft_pending_si_chaine_lcd_sans_decision(): void
    {
        $draft = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(self::YEAR)
            ->draft()
            ->create();

        $this->makeChainOfTwo();

        $state = $this->resolver->resolveForCompanyYear($this->company->id, self::YEAR);

        self::assertSame(DeclarationLifecycleState::DraftPending, $state->state);
        self::assertNotNull($state->currentDeclaration);
        self::assertSame($draft->id, $state->currentDeclaration->id);
        self::assertSame(1, $state->pendingClustersCount);
        self::assertFalse($state->canGenerate);
    }

    #[Test]
    public function s3_draft_ready_si_chaine_lcd_decidee(): void
    {
        FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(self::YEAR)
            ->draft()
            ->create();

        $this->makeChainOfTwo();

        // Le fingerprint doit matcher celui détecté par RiskDetectionService.
        $detection = $this->app->make(RiskDetectionService::class);
        $clusters = $detection->detectClusters($this->company->id, self::YEAR);
        self::assertCount(1, $clusters, 'Précondition : la chaîne LCD doit produire 1 cluster.');

        FiscalReviewDecision::factory()
            ->forCompany($this->company)
            ->forYear(self::YEAR)
            ->withFingerprint($clusters[0]->fingerprint)
            ->conserved()
            ->create(['decided_by' => $this->user->id]);

        $state = $this->resolver->resolveForCompanyYear($this->company->id, self::YEAR);

        self::assertSame(DeclarationLifecycleState::DraftReadyToGenerate, $state->state);
        self::assertSame(0, $state->pendingClustersCount);
        self::assertTrue($state->canGenerate);
    }

    #[Test]
    public function s4_deferred(): void
    {
        $deferred = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(self::YEAR)
            ->deferred()
            ->create();

        $state = $this->resolver->resolveForCompanyYear($this->company->id, self::YEAR);

        self::assertSame(DeclarationLifecycleState::Deferred, $state->state);
        self::assertSame($deferred->id, $state->currentDeclaration?->id);
        self::assertSame(0, $state->pendingClustersCount);
        self::assertFalse($state->canGenerate);
    }

    #[Test]
    public function s5_generated_active(): void
    {
        $generated = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(self::YEAR)
            ->generated()
            ->create();

        $state = $this->resolver->resolveForCompanyYear($this->company->id, self::YEAR);

        self::assertSame(DeclarationLifecycleState::GeneratedActive, $state->state);
        self::assertSame($generated->id, $state->currentDeclaration?->id);
        self::assertFalse($state->currentDeclaration->isObsolete);
        self::assertSame([], $state->obsoleteReasons);
    }

    #[Test]
    public function s6_generated_obsolete_orphan_expose_les_motifs(): void
    {
        $obsolete = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(self::YEAR)
            ->obsolete()
            ->create();

        $state = $this->resolver->resolveForCompanyYear($this->company->id, self::YEAR);

        self::assertSame(DeclarationLifecycleState::GeneratedObsoleteOrphan, $state->state);
        self::assertSame($obsolete->id, $state->currentDeclaration?->id);
        self::assertTrue($state->currentDeclaration->isObsolete);
        self::assertNull($state->predecessorDeclaration);
        self::assertCount(1, $state->obsoleteReasons);
        self::assertSame(InvalidationReasonType::ContractUpdated, $state->obsoleteReasons[0]->type);
    }

    #[Test]
    public function s7_regeneration_en_cours_pointe_vers_la_version_obsolete(): void
    {
        $previous = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(self::YEAR)
            ->obsolete()
            ->create();

        $draft = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(self::YEAR)
            ->draft()
            ->create();

        $previous->update(['superseded_by_id' => $draft->id]);

        $state = $this->resolver->resolveForCompanyYear($this->company->id, self::YEAR);

        self::assertSame(DeclarationLifecycleState::RegenerationInProgress, $state->state);
        self::assertSame($draft->id, $state->currentDeclaration?->id);
        self::assertSame($previous->id, $state->predecessorDeclaration?->id);
        self::assertCount(1, $state->obsoleteReasons);
        self::assertSame(
            InvalidationReasonType::ContractUpdated,
            $state->obsoleteReasons[0]->type,
        );
        self::assertCount(1, $state->historyChain);
        self::assertSame($previous->id, $state->historyChain[0]->id);
    }

    #[Test]
    public function history_chain_se_parcourt_de_la_plus_recente_a_la_plus_ancienne(): void
    {
        // Chaîne A (obsolète) → B (obsolète) → C (Draft courant).
        $a = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(self::YEAR)
            ->obsolete()
            ->create();

        $b = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(self::YEAR)
            ->obsolete()
            ->create();

        $c = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(self::YEAR)
            ->draft()
            ->create();

        $a->update(['superseded_by_id' => $b->id]);
        $b->update(['superseded_by_id' => $c->id]);

        $state = $this->resolver->resolveForCompanyYear($this->company->id, self::YEAR);

        self::assertSame(DeclarationLifecycleState::RegenerationInProgress, $state->state);
        self::assertSame($c->id, $state->currentDeclaration?->id);
        self::assertSame($b->id, $state->predecessorDeclaration?->id);
        self::assertCount(2, $state->historyChain);
        self::assertSame($b->id, $state->historyChain[0]->id);
        self::assertSame($a->id, $state->historyChain[1]->id);
    }

    /**
     * Crée une chaîne LCD minimale qualifiée : 2 contrats consécutifs
     * (intervalle 5 jours, cumul 40 jours) → 1 cluster R-LCD-CHAIN.
     */
    private function makeChainOfTwo(): void
    {
        Contract::factory()->create([
            'company_id' => $this->company->id,
            'vehicle_id' => $this->vehicle->id,
            'start_date' => sprintf('%d-01-01', self::YEAR),
            'end_date' => sprintf('%d-01-20', self::YEAR),
            'contract_type' => ContractType::Lcd,
        ]);
        Contract::factory()->create([
            'company_id' => $this->company->id,
            'vehicle_id' => $this->vehicle->id,
            'start_date' => sprintf('%d-01-26', self::YEAR),
            'end_date' => sprintf('%d-02-14', self::YEAR),
            'contract_type' => ContractType::Lcd,
        ]);
    }
}
