<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\FiscalDeclaration;

use App\Actions\FiscalDeclaration\GenerateDeclarationAction;
use App\Enums\Contract\ContractType;
use App\Enums\FiscalDeclaration\FiscalDeclarationStatus;
use App\Models\Company;
use App\Models\Contract;
use App\Models\FiscalDeclaration;
use App\Models\Vehicle;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class GenerateDeclarationActionTest extends TestCase
{
    use RefreshDatabase;

    private GenerateDeclarationAction $action;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->action = $this->app->make(GenerateDeclarationAction::class);
        $this->company = Company::factory()->create();
    }

    #[Test]
    public function genere_une_declaration_sans_clusters(): void
    {
        $declaration = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->draft()
            ->create();

        $generated = $this->action->execute($declaration->id);

        self::assertSame(FiscalDeclarationStatus::Generated, $generated->status);
        self::assertNotNull($generated->generated_at);
        self::assertNotNull($generated->generated_pdf_path);
        self::assertNotNull($generated->generated_pdf_hash);
        self::assertSame(64, strlen($generated->generated_pdf_hash));

        // PDF effectivement persisté sur le disque (Storage::fake)
        Storage::disk('local')->assertExists($generated->generated_pdf_path);
    }

    #[Test]
    public function refus_si_pending_clusters(): void
    {
        // Crée un cluster détectable mais sans décision persistée
        $vehicle = Vehicle::factory()->create();
        Contract::factory()->create([
            'company_id' => $this->company->id,
            'vehicle_id' => $vehicle->id,
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-20',
            'contract_type' => ContractType::Lcd,
        ]);
        Contract::factory()->create([
            'company_id' => $this->company->id,
            'vehicle_id' => $vehicle->id,
            'start_date' => '2025-01-26',
            'end_date' => '2025-02-14',
            'contract_type' => ContractType::Lcd,
        ]);

        $declaration = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->draft()
            ->create();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/cluster.*attente/');

        $this->action->execute($declaration->id);
    }

    #[Test]
    public function refus_si_pas_draft(): void
    {
        $declaration = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->generated()
            ->create();

        $this->expectException(DomainException::class);

        $this->action->execute($declaration->id);
    }

    #[Test]
    public function refus_si_obsolete(): void
    {
        $declaration = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->draft()
            ->create([
                'is_obsolete' => true,
                'obsolete_at' => now(),
            ]);

        $this->expectException(DomainException::class);

        $this->action->execute($declaration->id);
    }

    #[Test]
    public function refus_si_inexistante(): void
    {
        $this->expectException(DomainException::class);

        $this->action->execute(99999);
    }

    #[Test]
    public function le_path_pdf_suit_la_convention(): void
    {
        $declaration = FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear(2025)
            ->draft()
            ->create();

        $generated = $this->action->execute($declaration->id);

        self::assertStringContainsString(
            sprintf('declarations/%d/2025/', $this->company->id),
            $generated->generated_pdf_path,
        );
        self::assertStringEndsWith('.pdf', $generated->generated_pdf_path);
    }

    #[Test]
    public function la_reference_est_persistee_au_format_strict(): void
    {
        $company = Company::factory()->create(['short_code' => 'ACM']);
        $declaration = FiscalDeclaration::factory()
            ->forCompany($company)
            ->forYear(2025)
            ->draft()
            ->create();

        $generated = $this->action->execute($declaration->id);

        self::assertSame('DECL-ACM-2025-0001', $generated->reference);
    }

    #[Test]
    public function la_reference_increments_a_la_regeneration_sur_le_meme_couple(): void
    {
        $company = Company::factory()->create(['short_code' => 'ACM']);
        $first = FiscalDeclaration::factory()
            ->forCompany($company)
            ->forYear(2025)
            ->draft()
            ->create();
        $generatedFirst = $this->action->execute($first->id);
        self::assertSame('DECL-ACM-2025-0001', $generatedFirst->reference);

        $second = FiscalDeclaration::factory()
            ->forCompany($company)
            ->forYear(2025)
            ->draft()
            ->create();
        $generatedSecond = $this->action->execute($second->id);

        self::assertSame('DECL-ACM-2025-0002', $generatedSecond->reference);
    }
}
