<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\FiscalDeclaration;

use App\Actions\FiscalDeclaration\RegenerateDeclarationAction;
use App\Enums\FiscalDeclaration\FiscalDeclarationStatus;
use App\Models\Company;
use App\Models\FiscalDeclaration;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RegenerateDeclarationActionTest extends TestCase
{
    use RefreshDatabase;

    private RegenerateDeclarationAction $action;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = $this->app->make(RegenerateDeclarationAction::class);
        $this->company = Company::factory()->create();
    }

    #[Test]
    public function cree_un_nouveau_record_draft(): void
    {
        $obsolete = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->obsolete()
            ->create();

        $newDeclaration = $this->action->execute($obsolete->id);

        self::assertNotSame($obsolete->id, $newDeclaration->id);
        self::assertSame(FiscalDeclarationStatus::Draft, $newDeclaration->status);
        self::assertFalse($newDeclaration->is_obsolete);
        self::assertSame($this->company->id, $newDeclaration->company_id);
        self::assertSame(2025, $newDeclaration->fiscal_year);
    }

    #[Test]
    public function chaine_l_ancien_via_superseded_by_id(): void
    {
        $obsolete = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->obsolete()
            ->create();

        $newDeclaration = $this->action->execute($obsolete->id);

        self::assertSame($newDeclaration->id, $obsolete->fresh()->superseded_by_id);
    }

    #[Test]
    public function preserve_le_pdf_historique_intact(): void
    {
        $obsolete = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->obsolete()
            ->create([
                'generated_pdf_path' => 'declarations/1/2025/42.pdf',
                'generated_pdf_hash' => str_repeat('x', 64),
            ]);

        $this->action->execute($obsolete->id);

        $fresh = $obsolete->fresh();
        self::assertSame('declarations/1/2025/42.pdf', $fresh->generated_pdf_path);
        self::assertSame(str_repeat('x', 64), $fresh->generated_pdf_hash);
        self::assertTrue($fresh->is_obsolete);
    }

    #[Test]
    public function refus_si_declaration_non_obsolete(): void
    {
        $declaration = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->generated()
            ->create();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/obsol/');

        $this->action->execute($declaration->id);
    }

    #[Test]
    public function refus_si_inexistante(): void
    {
        $this->expectException(DomainException::class);

        $this->action->execute(99999);
    }
}
