<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\FiscalDeclaration\FiscalDeclarationStatus;
use App\Enums\FiscalDeclaration\InvalidationReasonType;
use App\Models\Company;
use App\Models\FiscalDeclaration;
use App\Models\FiscalReviewDecision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests du Model `FiscalDeclaration` (Phase 11 D1, ADR-0015 rev. 1.1) :
 * casts, relations, chaîne d'obsolescence, helper de récupération des
 * décisions liées par couple `(company, fiscal_year)`.
 */
final class FiscalDeclarationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function status_est_caste_en_enum(): void
    {
        $declaration = FiscalDeclaration::factory()->draft()->create();

        self::assertSame(FiscalDeclarationStatus::Draft, $declaration->status);
    }

    #[Test]
    public function obsolete_reasons_est_caste_en_array_avec_roundtrip(): void
    {
        $declaration = FiscalDeclaration::factory()->obsolete()->create();

        self::assertIsArray($declaration->obsolete_reasons);
        self::assertCount(1, $declaration->obsolete_reasons);
        self::assertSame(
            InvalidationReasonType::ContractUpdated->value,
            $declaration->obsolete_reasons[0]['type'],
        );
        self::assertSame('contract', $declaration->obsolete_reasons[0]['entity']['type']);
    }

    #[Test]
    public function relation_company_belongs_to(): void
    {
        $company = Company::factory()->create();
        $declaration = FiscalDeclaration::factory()->forCompany($company)->create();

        self::assertSame($company->id, $declaration->company->id);
    }

    #[Test]
    public function relation_superseded_by_pointe_vers_la_declaration_remplacante(): void
    {
        $oldDeclaration = FiscalDeclaration::factory()->generated()->create();
        $newDeclaration = FiscalDeclaration::factory()->generated()->create();

        $oldDeclaration->update(['superseded_by_id' => $newDeclaration->id]);

        self::assertSame($newDeclaration->id, $oldDeclaration->fresh()->supersededBy->id);
    }

    #[Test]
    public function relation_obsoletes_renvoie_les_declarations_remplacees(): void
    {
        $newDeclaration = FiscalDeclaration::factory()->generated()->create();
        $old1 = FiscalDeclaration::factory()->obsolete()->create(['superseded_by_id' => $newDeclaration->id]);
        $old2 = FiscalDeclaration::factory()->obsolete()->create(['superseded_by_id' => $newDeclaration->id]);

        $obsoletesIds = $newDeclaration->obsoletes->pluck('id')->all();

        self::assertContains($old1->id, $obsoletesIds);
        self::assertContains($old2->id, $obsoletesIds);
    }

    #[Test]
    public function suppression_du_remplacant_passe_les_anciens_en_superseded_null(): void
    {
        $newDeclaration = FiscalDeclaration::factory()->generated()->create();
        $oldDeclaration = FiscalDeclaration::factory()->obsolete()->create([
            'superseded_by_id' => $newDeclaration->id,
        ]);

        $newDeclaration->forceDelete();

        // La FK self est `nullOnDelete()` : l'enfant garde son existence mais
        // perd le lien (chaîne historique brisée seulement, pas effacée).
        self::assertNull($oldDeclaration->fresh()->superseded_by_id);
    }

    #[Test]
    public function helper_related_decisions_query_filtre_par_couple_company_year(): void
    {
        $company = Company::factory()->create();
        $declaration = FiscalDeclaration::factory()->forCompany($company)->forYear(2025)->create();

        // Décisions cibles : 2 sur le couple, 1 sur une autre année.
        $matching1 = FiscalReviewDecision::factory()->forCompany($company)->forYear(2025)->create();
        $matching2 = FiscalReviewDecision::factory()->forCompany($company)->forYear(2025)->create();
        FiscalReviewDecision::factory()->forCompany($company)->forYear(2024)->create();

        $found = $declaration->relatedDecisionsQuery()->get();

        self::assertCount(2, $found);
        $foundIds = $found->pluck('id')->all();
        self::assertContains($matching1->id, $foundIds);
        self::assertContains($matching2->id, $foundIds);
    }
}
