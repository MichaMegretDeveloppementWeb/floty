<?php

declare(strict_types=1);

namespace Tests\Feature\User\FiscalDeclaration;

use App\Enums\FiscalDeclaration\FiscalDeclarationStatus;
use App\Enums\FiscalReviewDecision\ReviewDecisionType;
use App\Enums\FiscalReviewDecision\RiskCode;
use App\Models\Company;
use App\Models\FiscalDeclaration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre les 9 endpoints du DeclarationController (Phase 11 D4).
 */
final class DeclarationControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->user = User::factory()->create();
        $this->company = Company::factory()->create();
        $this->actingAs($this->user);
    }

    #[Test]
    public function index_redirige_si_non_authentifie(): void
    {
        auth()->logout();

        $this->get('/app/declarations')->assertRedirect('/login');
    }

    #[Test]
    public function index_render_inertia_avec_pagination_et_options(): void
    {
        FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->draft()
            ->create();

        $this->get('/app/declarations')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/Declarations/Index/Index')
                ->has('declarations.data', 1)
                ->has('declarations.meta')
                ->has('options.companies')
                ->where('hasAnyDeclaration', true));
    }

    #[Test]
    public function index_has_any_false_si_aucune_declaration(): void
    {
        $this->get('/app/declarations')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('hasAnyDeclaration', false));
    }

    #[Test]
    public function prepare_cree_un_draft_et_redirige_vers_review(): void
    {
        $this->post('/app/declarations/prepare', [
            'company_id' => $this->company->id,
            'fiscal_year' => 2025,
        ])
            ->assertRedirect()
            ->assertSessionHas('toast-success');

        $this->assertSame(1, FiscalDeclaration::query()->count());
        $declaration = FiscalDeclaration::query()->firstOrFail();
        self::assertSame(FiscalDeclarationStatus::Draft, $declaration->status);
    }

    #[Test]
    public function prepare_refuse_si_declaration_active_existe(): void
    {
        FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->draft()
            ->create();

        $this->post('/app/declarations/prepare', [
            'company_id' => $this->company->id,
            'fiscal_year' => 2025,
        ])
            ->assertSessionHas('toast-error');

        // Toujours 1 seule déclaration en base (pas de doublon)
        self::assertSame(1, FiscalDeclaration::query()->count());
    }

    #[Test]
    public function show_render_inertia_avec_history_et_snapshot(): void
    {
        // P0.5 (audit perf 2026-05-16) · Generated avec payload
        // persiste → snapshot eager (lecture array quasi-instantanee,
        // pas de Inertia::defer). Le payload minimal explicite est
        // necessaire pour declencher la branche eager du ternaire ·
        // sans payload, le snapshot serait deferred (cas teste par
        // `show_servi_snapshot_en_inertia_defer_si_draft_sans_payload`).
        $declaration = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->generated()
            ->create([
                'generated_snapshot_payload' => [
                    'companyId' => $this->company->id,
                    'companyShortCode' => $this->company->short_code,
                    'companyLegalName' => $this->company->legal_name,
                    'fiscalYear' => 2025,
                    'computedAt' => '2025-12-31T23:59:59+01:00',
                    'co2DueTotal' => 0.0,
                    'pollutantsDueTotal' => 0.0,
                    'totalDue' => 0.0,
                    'contractBreakdown' => [],
                    'appliedDecisions' => [],
                    'optOutContractIds' => [],
                ],
            ]);

        $this->get(sprintf('/app/declarations/%d', $declaration->id))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/Declarations/Show/Index')
                ->where('declaration.id', $declaration->id)
                ->has('history')
                ->has('snapshot')
                ->has('snapshot.totalDue')
                ->has('snapshot.contractBreakdown')
                ->has('snapshot.appliedDecisions')
                ->has('snapshot.optOutContractIds'));
    }

    #[Test]
    public function show_servi_snapshot_en_inertia_defer_si_draft_sans_payload(): void
    {
        // P0.5 (audit perf 2026-05-16 / 08-misc.md P0 #2) · pour un
        // Draft sans payload persiste, le snapshot necessite un
        // engine->compute() complet (~100-500 ms). Servi en
        // `Inertia::defer` · pas dans la 1ere reponse Inertia, arrive
        // via une 2e requete asynchrone declenchee par <Deferred>
        // cote front.
        $declaration = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->draft()
            ->create();

        $this->get(sprintf('/app/declarations/%d', $declaration->id))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/Declarations/Show/Index')
                ->where('declaration.id', $declaration->id)
                ->has('history')
                ->missing('snapshot'));
    }

    #[Test]
    public function show_lit_le_snapshot_persiste_en_priorite_sans_recalculer(): void
    {
        // Crée une déclaration générée avec un snapshot persisté
        // arbitraire qui ne correspond pas au calcul standard. Si le
        // controller lit en priorité depuis BDD (audit B5 D5.7.5), il
        // doit retourner ces valeurs littérales, pas un recalcul.
        $persistedPayload = [
            'companyId' => $this->company->id,
            'companyShortCode' => $this->company->short_code,
            'companyLegalName' => $this->company->legal_name,
            'fiscalYear' => 2025,
            'computedAt' => '2025-12-31T23:59:59+01:00',
            'co2DueTotal' => 1234.56,
            'pollutantsDueTotal' => 78.90,
            'totalDue' => 1313.46,
            'contractBreakdown' => [],
            'appliedDecisions' => [],
            'optOutContractIds' => [],
        ];

        $declaration = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->generated()
            ->create([
                'reference' => 'DECL-TEST-2025-0001',
                'generated_snapshot_payload' => $persistedPayload,
            ]);

        $this->get(sprintf('/app/declarations/%d', $declaration->id))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/Declarations/Show/Index')
                ->where('snapshot.totalDue', 1313.46)
                ->where('snapshot.co2DueTotal', 1234.56)
                ->where('snapshot.pollutantsDueTotal', 78.90));
    }

    #[Test]
    public function show_expose_la_reference_si_declaration_generee(): void
    {
        $declaration = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->generated()
            ->create(['reference' => 'DECL-ACM-2025-0001']);

        $this->get(sprintf('/app/declarations/%d', $declaration->id))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/Declarations/Show/Index')
                ->where('declaration.reference', 'DECL-ACM-2025-0001'));
    }

    #[Test]
    public function review_redirige_vers_show_si_declaration_generated(): void
    {
        $declaration = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->generated()
            ->create();

        $this->get(sprintf('/app/declarations/%d/review', $declaration->id))
            ->assertRedirect(route('user.declarations.show', ['declaration' => $declaration->id]));
    }

    #[Test]
    public function review_redirige_vers_show_si_obsolete(): void
    {
        $declaration = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->obsolete()
            ->create();

        $this->get(sprintf('/app/declarations/%d/review', $declaration->id))
            ->assertRedirect(route('user.declarations.show', ['declaration' => $declaration->id]));
    }

    #[Test]
    public function review_render_inertia_si_draft_et_sert_preview_snapshot_en_defer(): void
    {
        // P0.4 (audit perf 2026-05-16 / 08-misc.md P0 #1) · `preview`
        // (RiskDetection clusters) et `snapshot` (Fiscal Engine) sont
        // servis en `Inertia::defer` pour ne pas bloquer le mount sur
        // ~300-800 ms de pipelines. Ce test prouve que le defer est
        // cable · les 2 props arrivent via une 2e requete asynchrone
        // partial reload declenchee par <Deferred> cote front. Le
        // contenu des pipelines est teste par les tests Unit dedies
        // (`DeclarationPreviewServiceTest`, `DeclarationFiscalEngineTest`).
        $declaration = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->draft()
            ->create();

        $this->get(sprintf('/app/declarations/%d/review', $declaration->id))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/Declarations/Review/Index')
                ->where('declaration.id', $declaration->id)
                ->missing('preview')
                ->missing('snapshot'));
    }

    #[Test]
    public function store_decision_persiste_et_back(): void
    {
        $declaration = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->draft()
            ->create();

        $this->post(sprintf('/app/declarations/%d/decisions', $declaration->id), [
            'company_id' => $this->company->id,
            'fiscal_year' => 2025,
            'risk_code' => RiskCode::Chain->value,
            'cluster_fingerprint' => str_repeat('a', 64),
            'decision' => ReviewDecisionType::Conserved->value,
            'justification' => 'Cluster moyen, contrats avec usage saisonnier',
        ])
            ->assertRedirect()
            ->assertSessionHas('toast-success');
    }

    #[Test]
    public function store_decision_refuse_si_perimetre_ne_correspond_pas(): void
    {
        $declaration = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->draft()
            ->create();

        $other = Company::factory()->create();

        $this->post(sprintf('/app/declarations/%d/decisions', $declaration->id), [
            'company_id' => $other->id, // Mismatch
            'fiscal_year' => 2025,
            'risk_code' => RiskCode::Chain->value,
            'cluster_fingerprint' => str_repeat('a', 64),
            'decision' => ReviewDecisionType::Conserved->value,
        ])
            ->assertRedirect()
            ->assertSessionHas('toast-error');
    }

    #[Test]
    public function mark_deferred_passe_le_statut_a_deferred(): void
    {
        $declaration = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->draft()
            ->create();

        $this->post(sprintf('/app/declarations/%d/mark-deferred', $declaration->id))
            ->assertRedirect()
            ->assertSessionHas('toast-success');

        self::assertSame(FiscalDeclarationStatus::Deferred, $declaration->fresh()->status);
    }

    #[Test]
    public function generate_redirige_vers_show_avec_pdf(): void
    {
        $declaration = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->draft()
            ->create();

        $this->post(sprintf('/app/declarations/%d/generate', $declaration->id))
            ->assertRedirect(route('user.declarations.show', ['declaration' => $declaration->id]))
            ->assertSessionHas('toast-success');

        $fresh = $declaration->fresh();
        self::assertSame(FiscalDeclarationStatus::Generated, $fresh->status);
        self::assertNotNull($fresh->generated_pdf_path);
        Storage::disk('local')->assertExists($fresh->generated_pdf_path);
    }

    #[Test]
    public function regenerate_cree_nouveau_draft_et_redirige_vers_review(): void
    {
        $obsolete = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->obsolete()
            ->create();

        $this->post(sprintf('/app/declarations/%d/regenerate', $obsolete->id))
            ->assertRedirect()
            ->assertSessionHas('toast-success');

        self::assertSame(2, FiscalDeclaration::query()->count());
        $newDeclaration = FiscalDeclaration::query()->where('status', 'draft')->firstOrFail();
        self::assertSame($newDeclaration->id, $obsolete->fresh()->superseded_by_id);
    }

    #[Test]
    public function download_renvoie_le_pdf_binary(): void
    {
        $declaration = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->draft()
            ->create();

        // Génération d'abord pour avoir un PDF persisté
        $this->post(sprintf('/app/declarations/%d/generate', $declaration->id));

        $response = $this->get(sprintf('/app/declarations/%d/download', $declaration->id));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    #[Test]
    public function download_renvoie_une_erreur_si_pas_de_pdf(): void
    {
        $declaration = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->draft()
            ->create();

        $response = $this->get(sprintf('/app/declarations/%d/download', $declaration->id));

        // Inertia peut transformer un 4xx en redirect en mode HTML.
        // L'invariant est : le PDF n'est pas servi (pas 200).
        self::assertNotSame(200, $response->status());
    }

    #[Test]
    public function modify_cree_nouveau_brouillon_et_obsolete_la_courante(): void
    {
        $generated = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->generated()
            ->create();

        $this->post(sprintf('/app/declarations/%d/modify', $generated->id))
            ->assertRedirect()
            ->assertSessionHas('toast-success');

        $previous = $generated->fresh();
        self::assertTrue($previous->is_obsolete);
        self::assertNotNull($previous->superseded_by_id);

        $newDraft = FiscalDeclaration::query()
            ->where('status', FiscalDeclarationStatus::Draft->value)
            ->where('company_id', $this->company->id)
            ->where('fiscal_year', 2025)
            ->firstOrFail();
        self::assertSame($newDraft->id, $previous->superseded_by_id);
    }

    #[Test]
    public function modify_refuse_si_declaration_deja_obsolete(): void
    {
        $obsolete = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->obsolete()
            ->create();

        $this->post(sprintf('/app/declarations/%d/modify', $obsolete->id))
            ->assertSessionHas('toast-error');

        // Pas de nouveau brouillon créé.
        self::assertSame(1, FiscalDeclaration::query()->count());
    }

    #[Test]
    public function destroy_soft_delete_le_brouillon(): void
    {
        $draft = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->draft()
            ->create();

        $this->delete(sprintf('/app/declarations/%d', $draft->id))
            ->assertRedirect(route('user.declarations.index'))
            ->assertSessionHas('toast-success');

        self::assertNotNull($draft->fresh()->deleted_at);
    }

    #[Test]
    public function destroy_refuse_si_declaration_generated(): void
    {
        $generated = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->generated()
            ->create();

        $this->delete(sprintf('/app/declarations/%d', $generated->id))
            ->assertSessionHas('toast-error');

        self::assertNull($generated->fresh()->deleted_at);
    }
}
