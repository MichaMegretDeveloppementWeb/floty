<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\FiscalReviewDecision\ReviewDecisionType;
use App\Enums\FiscalReviewDecision\RiskCode;
use App\Enums\FiscalReviewDecision\RiskLevel;
use App\Models\FiscalReviewDecision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests du Model `FiscalReviewDecision` (Phase 11 D1, ADR-0015 § 5.2) :
 * casts d'enums, relation utilisateur auteur, mapping RiskCode → RiskLevel.
 */
final class FiscalReviewDecisionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function risk_code_et_decision_sont_castes_en_enum(): void
    {
        $decision = FiscalReviewDecision::factory()->fortLevel()->requalified('justification test')->create();

        self::assertSame(RiskCode::ChainFort, $decision->risk_code);
        self::assertSame(ReviewDecisionType::Requalified, $decision->decision);
        self::assertSame(RiskLevel::Eleve, $decision->risk_code->level());
    }

    #[Test]
    public function relation_decided_by_renvoie_l_utilisateur(): void
    {
        $decision = FiscalReviewDecision::factory()->create();

        // PHPStan : `decidedBy` est typé non-null par PHPDoc, pas besoin
        // de `assertNotNull` qui serait toujours vrai.
        self::assertSame($decision->decided_by, $decision->decidedBy->id);
    }

    #[Test]
    public function chain_level_est_classe_moyen(): void
    {
        $decision = FiscalReviewDecision::factory()->create();

        self::assertSame(RiskCode::Chain, $decision->risk_code);
        self::assertSame(RiskLevel::Moyen, $decision->risk_code->level());
    }
}
