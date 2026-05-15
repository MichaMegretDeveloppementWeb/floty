<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\FiscalDeclaration;

use App\Actions\FiscalDeclaration\DiscardDraftDeclarationAction;
use App\Enums\FiscalDeclaration\FiscalDeclarationStatus;
use App\Enums\FiscalDeclaration\InvalidationReasonType;
use App\Enums\FiscalReviewDecision\ReviewDecisionType;
use App\Enums\FiscalReviewDecision\RiskCode;
use App\Models\Company;
use App\Models\FiscalDeclaration;
use App\Models\FiscalReviewDecision;
use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DiscardDraftDeclarationActionTest extends TestCase
{
    use RefreshDatabase;

    private DiscardDraftDeclarationAction $action;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = $this->app->make(DiscardDraftDeclarationAction::class);
        $this->company = Company::factory()->create();
    }

    #[Test]
    public function soft_delete_le_brouillon_sans_predecessor(): void
    {
        $draft = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->draft()
            ->create();

        $originalStatus = $this->action->execute($draft->id);

        self::assertSame(FiscalDeclarationStatus::Draft, $originalStatus);
        self::assertNotNull($draft->fresh()->deleted_at);
        self::assertNull(FiscalDeclaration::query()->find($draft->id));
    }

    #[Test]
    public function lot5_d2_retourne_le_statut_original_deferred(): void
    {
        // Lot 5 D2 · permet au controller de produire un toast
        // contextualisé (« Mise en attente annulée. ») au lieu d'un
        // « Brouillon supprimé. » indifférencié.
        $deferred = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->deferred()
            ->create();

        $originalStatus = $this->action->execute($deferred->id);

        self::assertSame(FiscalDeclarationStatus::Deferred, $originalStatus);
        self::assertNotNull($deferred->fresh()->deleted_at);
    }

    #[Test]
    public function lot5_d2_lock_pessimiste_rejette_si_statut_devient_generated_concurrence(): void
    {
        // Lot 5 D2 · simulation TOCTOU · si une mutation concurrente
        // change le statut Draft → Generated entre la lecture initiale
        // et le delete, le `softDeleteWithLock` doit lancer une
        // DomainException explicite (pas un soft delete silencieux d'une
        // Generated).
        $draft = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->draft()
            ->create();

        // Simule la mutation concurrente · on bascule en Generated avant
        // d'appeler l'Action. C'est l'équivalent fonctionnel d'une race
        // condition gagnée par un autre thread.
        $draft->update([
            'status' => FiscalDeclarationStatus::Generated,
            'generated_at' => now(),
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/mutation concurrente/');

        $this->action->execute($draft->id);
    }

    #[Test]
    public function reactivate_le_predecessor_si_obsolescence_volontaire_seulement(): void
    {
        $predecessor = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->generated()
            ->create([
                'is_obsolete' => true,
                'obsolete_at' => now(),
                'obsolete_reasons' => [
                    [
                        'type' => InvalidationReasonType::VoluntaryModification->value,
                        'occurred_at' => now()->toIso8601String(),
                        'actor_user_id' => 1,
                        'entity' => ['type' => 'user', 'id' => 1, 'label' => 'Admin'],
                        'fields_changed' => [],
                    ],
                ],
            ]);

        $draft = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->draft()
            ->create();

        $predecessor->update(['superseded_by_id' => $draft->id]);

        $this->action->execute($draft->id);

        $reactivated = $predecessor->fresh();
        self::assertFalse($reactivated->is_obsolete);
        self::assertNull($reactivated->obsolete_at);
        self::assertNull($reactivated->obsolete_reasons);
        self::assertNull($reactivated->superseded_by_id);
        self::assertSame(FiscalDeclarationStatus::Generated, $reactivated->status);
    }

    #[Test]
    public function garde_predecessor_obsolete_si_motifs_mixtes(): void
    {
        $predecessor = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->obsolete()
            ->create([
                'obsolete_reasons' => [
                    [
                        'type' => InvalidationReasonType::ContractCreated->value,
                        'occurred_at' => now()->toIso8601String(),
                        'actor_user_id' => 1,
                        'entity' => ['type' => 'contract', 'id' => 42, 'label' => 'EA-001'],
                        'fields_changed' => [],
                    ],
                    [
                        'type' => InvalidationReasonType::VoluntaryModification->value,
                        'occurred_at' => now()->toIso8601String(),
                        'actor_user_id' => 1,
                        'entity' => ['type' => 'user', 'id' => 1, 'label' => 'Admin'],
                        'fields_changed' => [],
                    ],
                ],
            ]);

        $draft = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->draft()
            ->create();

        $predecessor->update(['superseded_by_id' => $draft->id]);

        $this->action->execute($draft->id);

        $fresh = $predecessor->fresh();
        self::assertTrue($fresh->is_obsolete);
        self::assertNotNull($fresh->obsolete_at);
        self::assertCount(2, $fresh->obsolete_reasons);
        self::assertNull($fresh->superseded_by_id);
    }

    #[Test]
    public function garde_predecessor_obsolete_si_motifs_perimetre_uniquement(): void
    {
        $predecessor = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->obsolete()
            ->create();

        $draft = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->draft()
            ->create();

        $predecessor->update(['superseded_by_id' => $draft->id]);

        $this->action->execute($draft->id);

        $fresh = $predecessor->fresh();
        self::assertTrue($fresh->is_obsolete);
        self::assertNull($fresh->superseded_by_id);
    }

    #[Test]
    public function refus_si_declaration_generated(): void
    {
        $generated = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->generated()
            ->create();

        $this->expectException(DomainException::class);
        // Lot 5 D2 · le message vient désormais du `softDeleteWithLock`
        // (statut hors liste autorisée = mutation concurrente côté repo).
        $this->expectExceptionMessageMatches('/n\'existe plus ou n\'est plus dans un statut supprimable/');

        $this->action->execute($generated->id);
    }

    #[Test]
    public function refus_si_inexistante(): void
    {
        $this->expectException(DomainException::class);

        $this->action->execute(99_999);
    }

    #[Test]
    public function efface_les_decisions_de_revue_du_couple_company_year(): void
    {
        // D5.10.R · une décision de cluster (Conserved / Requalified) ne
        // doit pas survivre à la suppression du brouillon · sinon elle
        // se rechargerait automatiquement au prochain brouillon créé sur
        // le même (company, year).
        $user = User::factory()->create();
        $draft = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->draft()
            ->create();

        // Persiste 2 décisions du couple (company, 2025).
        FiscalReviewDecision::query()->create([
            'company_id' => $this->company->id,
            'fiscal_year' => 2025,
            'risk_code' => RiskCode::Chain,
            'cluster_fingerprint' => str_repeat('a', 64),
            'decision' => ReviewDecisionType::Conserved,
            'justification' => 'Pas d\'abus',
            'excluded_contract_ids' => null,
            'decided_by' => $user->id,
            'decided_at' => now(),
        ]);
        FiscalReviewDecision::query()->create([
            'company_id' => $this->company->id,
            'fiscal_year' => 2025,
            'risk_code' => RiskCode::Chain,
            'cluster_fingerprint' => str_repeat('b', 64),
            'decision' => ReviewDecisionType::Requalified,
            'justification' => null,
            'excluded_contract_ids' => null,
            'decided_by' => $user->id,
            'decided_at' => now(),
        ]);

        // Décision d'un AUTRE couple (autre année) · ne doit PAS être affectée.
        FiscalReviewDecision::query()->create([
            'company_id' => $this->company->id,
            'fiscal_year' => 2024,
            'risk_code' => RiskCode::Chain,
            'cluster_fingerprint' => str_repeat('c', 64),
            'decision' => ReviewDecisionType::Conserved,
            'justification' => null,
            'excluded_contract_ids' => null,
            'decided_by' => $user->id,
            'decided_at' => now(),
        ]);

        self::assertSame(2, FiscalReviewDecision::query()
            ->where('company_id', $this->company->id)
            ->where('fiscal_year', 2025)
            ->count());

        $this->action->execute($draft->id);

        // Les 2 décisions du couple (company, 2025) sont effacées.
        self::assertSame(0, FiscalReviewDecision::query()
            ->where('company_id', $this->company->id)
            ->where('fiscal_year', 2025)
            ->count());

        // La décision d'une autre année reste intacte.
        self::assertSame(1, FiscalReviewDecision::query()
            ->where('company_id', $this->company->id)
            ->where('fiscal_year', 2024)
            ->count());
    }
}
