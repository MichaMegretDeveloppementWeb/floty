<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\FiscalDeclaration;

use App\Actions\FiscalDeclaration\StoreReviewDecisionAction;
use App\Data\User\FiscalReviewDecision\StoreReviewDecisionData;
use App\Enums\Contract\ContractType;
use App\Enums\FiscalReviewDecision\ReviewDecisionType;
use App\Enums\FiscalReviewDecision\RiskCode;
use App\Models\Company;
use App\Models\Contract;
use App\Models\FiscalReviewDecision;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class StoreReviewDecisionActionTest extends TestCase
{
    use RefreshDatabase;

    private StoreReviewDecisionAction $action;

    private Company $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = $this->app->make(StoreReviewDecisionAction::class);
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create();
    }

    #[Test]
    public function cree_une_nouvelle_decision(): void
    {
        $data = $this->makeData(
            decision: ReviewDecisionType::Requalified,
            justification: null,
        );

        $decision = $this->action->execute($data, $this->user->id);

        self::assertGreaterThan(0, $decision->id);
        self::assertSame(ReviewDecisionType::Requalified, $decision->decision);
        self::assertSame($this->user->id, $decision->decided_by);
        self::assertTrue($decision->decided_at->lessThanOrEqualTo(now()));
    }

    #[Test]
    public function upsert_par_fingerprint_remplace_la_decision_existante(): void
    {
        $fp = str_repeat('a', 64);

        $first = $this->action->execute(
            $this->makeData(
                fingerprint: $fp,
                decision: ReviewDecisionType::Requalified,
            ),
            $this->user->id,
        );

        $second = $this->action->execute(
            $this->makeData(
                fingerprint: $fp,
                decision: ReviewDecisionType::Conserved,
                justification: 'Changement d\'avis',
            ),
            $this->user->id,
        );

        self::assertSame($first->id, $second->id);
        self::assertSame(ReviewDecisionType::Conserved, $second->decision);
        self::assertSame('Changement d\'avis', $second->justification);
        self::assertSame(1, FiscalReviewDecision::query()->count());
    }

    #[Test]
    public function refuse_conserved_sans_justification_si_niveau_eleve(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->action->execute(
            $this->makeData(
                riskCode: RiskCode::ChainFort,
                decision: ReviewDecisionType::Conserved,
                justification: null,
            ),
            $this->user->id,
        );
    }

    #[Test]
    public function refuse_conserved_avec_justification_blanche_si_niveau_eleve(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->action->execute(
            $this->makeData(
                riskCode: RiskCode::ChainFort,
                decision: ReviewDecisionType::Conserved,
                justification: '   ',
            ),
            $this->user->id,
        );
    }

    #[Test]
    public function accepte_conserved_sans_justification_si_niveau_moyen(): void
    {
        $decision = $this->action->execute(
            $this->makeData(
                riskCode: RiskCode::Chain,
                decision: ReviewDecisionType::Conserved,
                justification: null,
            ),
            $this->user->id,
        );

        self::assertNull($decision->justification);
    }

    #[Test]
    public function accepte_requalified_sans_justification_meme_niveau_eleve(): void
    {
        // Requalifier ne nécessite pas de justification (l'utilisateur
        // applique le verdict prudent recommandé par le système).
        $decision = $this->action->execute(
            $this->makeData(
                riskCode: RiskCode::ChainFort,
                decision: ReviewDecisionType::Requalified,
                justification: null,
            ),
            $this->user->id,
        );

        self::assertSame(ReviewDecisionType::Requalified, $decision->decision);
    }

    #[Test]
    public function refuse_si_justification_depasse_2000_caracteres(): void
    {
        // Audit B17 pré-livraison : limite défensive contre le
        // débordement du champ TEXT BDD ou stockage disproportionné.
        $longJustification = str_repeat('a', 2001);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/2000 caractères/');

        $this->action->execute(
            $this->makeData(
                decision: ReviewDecisionType::Requalified,
                justification: $longJustification,
            ),
            $this->user->id,
        );
    }

    #[Test]
    public function accepte_justification_a_la_limite_exacte_2000_caracteres(): void
    {
        $exactlyAtLimit = str_repeat('é', 2000); // 2000 caractères UTF-8

        $decision = $this->action->execute(
            $this->makeData(
                decision: ReviewDecisionType::Requalified,
                justification: $exactlyAtLimit,
            ),
            $this->user->id,
        );

        self::assertSame($exactlyAtLimit, $decision->justification);
    }

    // ---------- Lot 5 D4 (F-19D2-001) · validation excludedContractIds ----------

    #[Test]
    public function lot5_d4_accepte_excluded_contract_ids_appartenant_au_couple_company_year(): void
    {
        // Lot 5 D4 · les `excludedContractIds` doivent appartenir au
        // couple `(company_id, fiscal_year)` de la décision. Cas
        // nominal · 2 contrats LCD valides du même couple.
        $vehicle = Vehicle::factory()->create();
        $contract1 = Contract::factory()->create([
            'company_id' => $this->company->id,
            'vehicle_id' => $vehicle->id,
            'start_date' => '2025-03-01',
            'end_date' => '2025-03-15',
            'contract_type' => ContractType::Lcd,
        ]);
        $contract2 = Contract::factory()->create([
            'company_id' => $this->company->id,
            'vehicle_id' => Vehicle::factory()->create()->id,
            'start_date' => '2025-03-20',
            'end_date' => '2025-04-05',
            'contract_type' => ContractType::Lcd,
        ]);

        $data = $this->makeData(
            decision: ReviewDecisionType::Requalified,
            excludedContractIds: [$contract1->id, $contract2->id],
        );

        $stored = $this->action->execute($data, $this->user->id);

        self::assertSame([$contract1->id, $contract2->id], $stored->excluded_contract_ids);
    }

    #[Test]
    public function lot5_d4_accepte_excluded_contract_ids_null_ou_vide(): void
    {
        // Lot 5 D4 · null ou tableau vide doit passer sans déclencher
        // le repo Contracts (économie d'1 query inutile).
        $stored = $this->action->execute(
            $this->makeData(
                decision: ReviewDecisionType::Requalified,
                excludedContractIds: null,
            ),
            $this->user->id,
        );
        self::assertNull($stored->excluded_contract_ids);

        $stored2 = $this->action->execute(
            $this->makeData(
                fingerprint: str_repeat('c', 64),
                decision: ReviewDecisionType::Requalified,
                excludedContractIds: [],
            ),
            $this->user->id,
        );
        self::assertNull($stored2->excluded_contract_ids);
    }

    #[Test]
    public function lot5_d4_refuse_excluded_contract_id_etranger_au_couple(): void
    {
        // Lot 5 D4 · risque IDOR latent V2 multi-tenant · un contrat
        // appartenant à une AUTRE entreprise doit être rejeté avec un
        // message clair listant les IDs offensants.
        $otherCompany = Company::factory()->create();
        $otherContract = Contract::factory()->create([
            'company_id' => $otherCompany->id,
            'vehicle_id' => Vehicle::factory()->create()->id,
            'start_date' => '2025-03-01',
            'end_date' => '2025-03-15',
            'contract_type' => ContractType::Lcd,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/n\'appartiennent pas à l\'entreprise/');

        $this->action->execute(
            $this->makeData(
                decision: ReviewDecisionType::Requalified,
                excludedContractIds: [$otherContract->id],
            ),
            $this->user->id,
        );
    }

    #[Test]
    public function lot5_d4_refuse_excluded_contract_id_appartenant_a_une_autre_annee(): void
    {
        // Lot 5 D4 · même entreprise mais année fiscale différente
        // (contrat 2024 dans une décision 2025). Doit être rejeté
        // car le couple (company, year) ne matche pas.
        $vehicle = Vehicle::factory()->create();
        $contract2024 = Contract::factory()->create([
            'company_id' => $this->company->id,
            'vehicle_id' => $vehicle->id,
            'start_date' => '2024-06-01',
            'end_date' => '2024-06-20',
            'contract_type' => ContractType::Lcd,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/exercice 2025/');

        $this->action->execute(
            $this->makeData(
                decision: ReviewDecisionType::Requalified,
                excludedContractIds: [$contract2024->id],
            ),
            $this->user->id,
        );
    }

    /**
     * @param  list<int>|null  $excludedContractIds
     */
    private function makeData(
        ?string $fingerprint = null,
        RiskCode $riskCode = RiskCode::Chain,
        ReviewDecisionType $decision = ReviewDecisionType::Conserved,
        ?string $justification = null,
        ?array $excludedContractIds = null,
    ): StoreReviewDecisionData {
        return new StoreReviewDecisionData(
            companyId: $this->company->id,
            fiscalYear: 2025,
            riskCode: $riskCode,
            clusterFingerprint: $fingerprint ?? str_repeat('b', 64),
            decision: $decision,
            justification: $justification,
            excludedContractIds: $excludedContractIds,
        );
    }
}
