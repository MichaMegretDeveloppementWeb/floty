<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\FiscalDeclaration;

use App\Actions\FiscalDeclaration\StoreReviewDecisionAction;
use App\Data\User\FiscalReviewDecision\StoreReviewDecisionData;
use App\Enums\FiscalReviewDecision\ReviewDecisionType;
use App\Enums\FiscalReviewDecision\RiskCode;
use App\Models\Company;
use App\Models\FiscalReviewDecision;
use App\Models\User;
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

        self::assertNotNull($decision->id);
        self::assertSame(ReviewDecisionType::Requalified, $decision->decision);
        self::assertSame($this->user->id, $decision->decided_by);
        self::assertNotNull($decision->decided_at);
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

    private function makeData(
        ?string $fingerprint = null,
        RiskCode $riskCode = RiskCode::Chain,
        ReviewDecisionType $decision = ReviewDecisionType::Conserved,
        ?string $justification = null,
    ): StoreReviewDecisionData {
        return new StoreReviewDecisionData(
            companyId: $this->company->id,
            fiscalYear: 2025,
            riskCode: $riskCode,
            clusterFingerprint: $fingerprint ?? str_repeat('b', 64),
            decision: $decision,
            justification: $justification,
        );
    }
}
