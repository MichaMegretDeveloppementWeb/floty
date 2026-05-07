<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\FiscalDeclaration;

use App\Actions\FiscalDeclaration\CreateDraftDeclarationAction;
use App\Enums\FiscalDeclaration\FiscalDeclarationStatus;
use App\Models\Company;
use App\Models\FiscalDeclaration;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CreateDraftDeclarationActionTest extends TestCase
{
    use RefreshDatabase;

    private CreateDraftDeclarationAction $action;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = $this->app->make(CreateDraftDeclarationAction::class);
        $this->company = Company::factory()->create();
    }

    #[Test]
    public function cree_un_record_draft_pour_un_couple_company_year_vierge(): void
    {
        $declaration = $this->action->execute($this->company->id, 2025);

        self::assertSame(FiscalDeclarationStatus::Draft, $declaration->status);
        self::assertSame($this->company->id, $declaration->company_id);
        self::assertSame(2025, $declaration->fiscal_year);
        self::assertFalse($declaration->is_obsolete);
    }

    #[Test]
    public function refuse_si_declaration_active_existe_deja(): void
    {
        FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->draft()
            ->create();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('déclaration active');

        $this->action->execute($this->company->id, 2025);
    }

    #[Test]
    public function autorise_si_seules_declarations_obsoletes_existent(): void
    {
        FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->obsolete()
            ->create();

        $declaration = $this->action->execute($this->company->id, 2025);

        self::assertSame(FiscalDeclarationStatus::Draft, $declaration->status);
        self::assertFalse($declaration->is_obsolete);
        self::assertSame(2, FiscalDeclaration::query()->count());
    }
}
