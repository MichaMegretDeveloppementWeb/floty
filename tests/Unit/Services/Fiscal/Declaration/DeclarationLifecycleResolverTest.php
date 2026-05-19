<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Fiscal\Declaration;

use App\Data\User\FiscalDeclaration\DeclarationListItemData;
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
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
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

    // ---------- Lot 5 D5 : robustesse lifecycle (F-19-008 + F-19D2-013) ----------

    #[Test]
    public function lot5_d5_build_history_chain_detecte_cycle_et_break(): void
    {
        // F-19-008 : simulation TOCTOU pathologique : 2 déclarations
        // qui se référencent mutuellement via `superseded_by_id` (cas
        // impossible naturellement en BDD car chaque déclaration n'a
        // qu'un seul `superseded_by_id`, mais reproductible via
        // double-update direct simulant un bug applicatif futur).
        // L'algorithme doit stopper au re-visit, pas boucler infiniment.
        // Test via reflection sur la méthode privée (le cycle backward
        // ne peut pas être déclenché via `resolveForCompanyYear` car
        // aucune déclaration cycliquée n'aurait `superseded_by_id IS NULL`
        // et donc ne serait jamais identifiée comme `current`).
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

        // Cycle direct A ↔ B : chacun pointe vers l'autre.
        $a->update(['superseded_by_id' => $b->id]);
        $b->update(['superseded_by_id' => $a->id]);

        $method = new ReflectionMethod($this->resolver, 'buildHistoryChain');
        /** @var list<DeclarationListItemData> $chain */
        $chain = $method->invoke($this->resolver, $a);

        // 2 déclarations dans la chaîne (A puis B trouvé via
        // findPredecessorOf), puis break au re-visit de A.
        self::assertCount(2, $chain);
        self::assertSame($a->id, $chain[0]->id);
        self::assertSame($b->id, $chain[1]->id);
    }

    #[Test]
    public function lot5_d5_resolve_obsolete_reasons_renvoie_vide_si_type_non_array(): void
    {
        // F-19D2-013 : si `obsolete_reasons` BDD contient une valeur
        // non-array (ex. JSON scalar string : cast Eloquent renvoie
        // alors le string non-décodé en PHP au lieu d'un array), le
        // garde-fou retourne array vide au lieu de crasher : le canal
        // log warning garde la trace pour audit forensic.
        $generated = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(self::YEAR)
            ->generated()
            ->create();

        // Update direct via DB::table pour bypasser le cast 'array'
        // d'Eloquent à l'écriture : MySQL stocke un JSON string scalar.
        DB::table('fiscal_declarations')
            ->where('id', $generated->id)
            ->update([
                'is_obsolete' => true,
                'obsolete_at' => now(),
                'obsolete_reasons' => '"corrupted-not-an-array"',
            ]);

        $state = $this->resolver->resolveForCompanyYear($this->company->id, self::YEAR);

        self::assertSame(DeclarationLifecycleState::GeneratedObsoleteOrphan, $state->state);
        self::assertSame([], $state->obsoleteReasons);
    }

    #[Test]
    public function lot5_d5_resolve_obsolete_reasons_renvoie_vide_si_entree_invalide(): void
    {
        // F-19D2-013 : si `obsolete_reasons` est un array mais avec
        // une entrée mal structurée (champ requis manquant pour
        // `InvalidationReasonData::fromArray`), le garde-fou intercepte
        // le Throwable et retourne array vide au lieu de propager
        // l'erreur jusqu'à la fiche Company.
        $generated = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(self::YEAR)
            ->generated()
            ->create();

        DB::table('fiscal_declarations')
            ->where('id', $generated->id)
            ->update([
                'is_obsolete' => true,
                'obsolete_at' => now(),
                // Array valide mais entrée incomplète (manque `type`)
                'obsolete_reasons' => json_encode([
                    ['actor_user_id' => 1, 'occurred_at' => '2026-05-15T10:00:00Z'],
                ]),
            ]);

        $state = $this->resolver->resolveForCompanyYear($this->company->id, self::YEAR);

        self::assertSame(DeclarationLifecycleState::GeneratedObsoleteOrphan, $state->state);
        self::assertSame([], $state->obsoleteReasons);
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
