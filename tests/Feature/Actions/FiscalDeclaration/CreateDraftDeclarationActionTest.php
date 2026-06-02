<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\FiscalDeclaration;

use App\Actions\FiscalDeclaration\CreateDraftDeclarationAction;
use App\Enums\FiscalDeclaration\FiscalDeclarationStatus;
use App\Models\Company;
use App\Models\FiscalDeclaration;
use Carbon\CarbonImmutable;
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
        $this->expectExceptionMessageMatches('/déclaration 2025 existe déjà/');

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

    #[Test]
    public function refuse_la_creation_pour_lannee_courante(): void
    {
        // P5 · doctrine CIBS · la déclaration N est due au 30/04/N+1 ·
        // on ne peut pas la préparer tant que l'année fiscale n'est
        // pas terminée.
        CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 5, 15));

        try {
            $this->expectException(DomainException::class);
            $this->action->execute($this->company->id, 2026);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    #[Test]
    public function refuse_la_creation_pour_une_annee_future(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 5, 15));

        try {
            $this->expectException(DomainException::class);
            $this->action->execute($this->company->id, 2027);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    #[Test]
    public function refuse_la_creation_pour_une_annee_sans_regles_fiscales(): void
    {
        // 2023 est une année close (2023 < 2026) mais SANS règles fiscales
        // codées (le registre couvre 2024-2026). La préparation est bloquée
        // en amont avec un message dédié, pas un 500 à la génération.
        CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 5, 15));

        try {
            $this->expectException(DomainException::class);
            $this->expectExceptionMessageMatches('/Aucune règle fiscale/');
            $this->action->execute($this->company->id, 2023);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    #[Test]
    public function autorise_pour_lannee_precedente(): void
    {
        // Cas nominal · année close, préparation autorisée.
        CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 5, 15));

        try {
            $declaration = $this->action->execute($this->company->id, 2025);
            self::assertSame(FiscalDeclarationStatus::Draft, $declaration->status);
            self::assertSame(2025, $declaration->fiscal_year);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }
}
