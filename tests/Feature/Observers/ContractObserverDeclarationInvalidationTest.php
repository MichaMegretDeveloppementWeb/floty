<?php

declare(strict_types=1);

namespace Tests\Feature\Observers;

use App\Enums\Contract\ContractType;
use App\Enums\FiscalDeclaration\InvalidationReasonType;
use App\Models\Company;
use App\Models\Contract;
use App\Models\FiscalDeclaration;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre l'extension `ContractObserver` pour l'invalidation des
 * déclarations fiscales (Phase 11 D3, ADR-0015 § D8).
 *
 * Vérifie aussi la NON-invalidation sur les actions non-invalidantes
 * (mise à jour de `contract_reference` ou `notes` seules).
 */
final class ContractObserverDeclarationInvalidationTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Vehicle $vehicle;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        $this->vehicle = Vehicle::factory()->create();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    #[Test]
    public function created_invalide_la_declaration_de_l_annee(): void
    {
        $declaration = $this->makeDeclaration(2025);

        Contract::factory()->create([
            'company_id' => $this->company->id,
            'vehicle_id' => $this->vehicle->id,
            'start_date' => '2025-03-01',
            'end_date' => '2025-03-15',
            'contract_type' => ContractType::Lcd,
        ]);

        $fresh = $declaration->fresh();
        self::assertTrue($fresh->is_obsolete);
        self::assertSame(
            InvalidationReasonType::ContractCreated->value,
            $fresh->obsolete_reasons[0]['type'],
        );
    }

    #[Test]
    public function updated_sur_dates_invalide(): void
    {
        $contract = Contract::factory()->create([
            'company_id' => $this->company->id,
            'vehicle_id' => $this->vehicle->id,
            'start_date' => '2025-03-01',
            'end_date' => '2025-03-15',
            'contract_type' => ContractType::Lcd,
        ]);

        // Crée la déclaration APRÈS le contract (sinon le created flag déjà)
        $declaration = $this->makeDeclaration(2025);
        self::assertFalse($declaration->is_obsolete);

        $contract->update(['end_date' => '2025-03-20']);

        $fresh = $declaration->fresh();
        self::assertTrue($fresh->is_obsolete);
        self::assertSame(
            InvalidationReasonType::ContractUpdated->value,
            $fresh->obsolete_reasons[0]['type'],
        );
    }

    #[Test]
    public function updated_sur_contract_reference_n_invalide_pas(): void
    {
        $contract = Contract::factory()->create([
            'company_id' => $this->company->id,
            'vehicle_id' => $this->vehicle->id,
            'start_date' => '2025-03-01',
            'end_date' => '2025-03-15',
            'contract_type' => ContractType::Lcd,
        ]);
        $declaration = $this->makeDeclaration(2025);
        self::assertFalse($declaration->is_obsolete);

        $contract->update(['contract_reference' => 'NEW-REF-123']);

        self::assertFalse($declaration->fresh()->is_obsolete);
    }

    #[Test]
    public function updated_sur_notes_n_invalide_pas(): void
    {
        $contract = Contract::factory()->create([
            'company_id' => $this->company->id,
            'vehicle_id' => $this->vehicle->id,
            'start_date' => '2025-03-01',
            'end_date' => '2025-03-15',
            'contract_type' => ContractType::Lcd,
        ]);
        $declaration = $this->makeDeclaration(2025);

        $contract->update(['notes' => 'Notes ajoutées plus tard']);

        self::assertFalse($declaration->fresh()->is_obsolete);
    }

    #[Test]
    public function deleted_invalide(): void
    {
        $contract = Contract::factory()->create([
            'company_id' => $this->company->id,
            'vehicle_id' => $this->vehicle->id,
            'start_date' => '2025-03-01',
            'end_date' => '2025-03-15',
            'contract_type' => ContractType::Lcd,
        ]);
        $declaration = $this->makeDeclaration(2025);

        $contract->delete();

        $fresh = $declaration->fresh();
        self::assertTrue($fresh->is_obsolete);
        self::assertSame(
            InvalidationReasonType::ContractDeleted->value,
            $fresh->obsolete_reasons[0]['type'],
        );
    }

    #[Test]
    public function created_a_cheval_invalide_les_2_annees(): void
    {
        $decl2024 = $this->makeDeclaration(2024);
        $decl2025 = $this->makeDeclaration(2025);

        Contract::factory()->create([
            'company_id' => $this->company->id,
            'vehicle_id' => $this->vehicle->id,
            'start_date' => '2024-12-26',
            'end_date' => '2025-01-15',
            'contract_type' => ContractType::Lcd,
        ]);

        self::assertTrue($decl2024->fresh()->is_obsolete);
        self::assertTrue($decl2025->fresh()->is_obsolete);
    }

    private function makeDeclaration(int $year): FiscalDeclaration
    {
        return FiscalDeclaration::factory()
            ->forCompany($this->company)
            ->forYear($year)
            ->generated()
            ->create();
    }
}
