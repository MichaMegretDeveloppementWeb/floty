<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Fiscal\Declaration;

use App\Models\Company;
use App\Models\FiscalDeclaration;
use App\Services\Fiscal\Declaration\DeclarationReferenceGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Couvre {@see DeclarationReferenceGenerator} (Phase 11 D5.3).
 *
 * Vérifications :
 *   - format strict `DECL-{shortCode}-{year}-{NNNN}`
 *   - compteur séquentiel par couple, démarrant à 0001
 *   - compteurs indépendants par company et par année
 *   - soft-deleted comptés (séquence ne recule pas après obsolescence)
 *   - throw si company introuvable
 */
final class DeclarationReferenceGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private DeclarationReferenceGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = $this->app->make(DeclarationReferenceGenerator::class);
    }

    #[Test]
    public function premiere_generation_pour_un_couple_renvoie_un_compteur_a_zero_zero_zero_un(): void
    {
        $company = Company::factory()->create(['short_code' => 'ACM']);

        $reference = $this->generator->generateFor($company->id, 2024);

        self::assertSame('DECL-ACM-2024-0001', $reference);
    }

    #[Test]
    public function generation_suivante_sur_le_meme_couple_incremente_le_compteur(): void
    {
        $company = Company::factory()->create(['short_code' => 'ACM']);
        FiscalDeclaration::factory()->create([
            'company_id' => $company->id,
            'fiscal_year' => 2024,
            'reference' => 'DECL-ACM-2024-0001',
        ]);

        $reference = $this->generator->generateFor($company->id, 2024);

        self::assertSame('DECL-ACM-2024-0002', $reference);
    }

    #[Test]
    public function les_compteurs_sont_independants_par_annee_pour_la_meme_company(): void
    {
        $company = Company::factory()->create(['short_code' => 'ACM']);
        FiscalDeclaration::factory()->create([
            'company_id' => $company->id,
            'fiscal_year' => 2024,
            'reference' => 'DECL-ACM-2024-0001',
        ]);

        $ref2024 = $this->generator->generateFor($company->id, 2024);
        $ref2025 = $this->generator->generateFor($company->id, 2025);

        self::assertSame('DECL-ACM-2024-0002', $ref2024);
        self::assertSame('DECL-ACM-2025-0001', $ref2025);
    }

    #[Test]
    public function les_compteurs_sont_independants_par_company_pour_la_meme_annee(): void
    {
        $acm = Company::factory()->create(['short_code' => 'ACM']);
        $dek = Company::factory()->create(['short_code' => 'DEK']);
        FiscalDeclaration::factory()->create([
            'company_id' => $acm->id,
            'fiscal_year' => 2024,
            'reference' => 'DECL-ACM-2024-0001',
        ]);

        $refAcm = $this->generator->generateFor($acm->id, 2024);
        $refDek = $this->generator->generateFor($dek->id, 2024);

        self::assertSame('DECL-ACM-2024-0002', $refAcm);
        self::assertSame('DECL-DEK-2024-0001', $refDek);
    }

    #[Test]
    public function les_declarations_soft_deleted_comptent_pour_le_sequencement(): void
    {
        $company = Company::factory()->create(['short_code' => 'ACM']);
        $first = FiscalDeclaration::factory()->create([
            'company_id' => $company->id,
            'fiscal_year' => 2024,
            'reference' => 'DECL-ACM-2024-0001',
        ]);
        $first->delete(); // soft-delete

        $reference = $this->generator->generateFor($company->id, 2024);

        self::assertSame(
            'DECL-ACM-2024-0002',
            $reference,
            'La séquence ne recule jamais ; soft-deleted comptés.',
        );
    }

    #[Test]
    public function les_declarations_sans_reference_ne_sont_pas_comptees(): void
    {
        $company = Company::factory()->create(['short_code' => 'ACM']);
        // Une déclaration Draft sans référence (cas pré-D5.5).
        FiscalDeclaration::factory()->create([
            'company_id' => $company->id,
            'fiscal_year' => 2024,
            'reference' => null,
        ]);

        $reference = $this->generator->generateFor($company->id, 2024);

        self::assertSame('DECL-ACM-2024-0001', $reference);
    }

    #[Test]
    public function company_introuvable_leve_runtime_exception(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Entreprise 99999 introuvable.');

        $this->generator->generateFor(99999, 2024);
    }
}
