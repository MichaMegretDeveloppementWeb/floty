<?php

declare(strict_types=1);

namespace Tests\Feature\Observers;

use App\Enums\Contract\ContractType;
use App\Enums\FiscalDeclaration\InvalidationReasonType;
use App\Enums\Vehicle\VehicleExitReason;
use App\Enums\Vehicle\VehicleStatus;
use App\Models\Company;
use App\Models\Contract;
use App\Models\FiscalDeclaration;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre {@see App\Observers\VehicleObserver} pour l'invalidation
 * des déclarations fiscales lorsqu'`exit_date` change (Phase 11
 * D5.7.8 audit pré-livraison T3).
 *
 * **Pourquoi critique** : avant ce test (et le fix associé), une
 * modification d'`exit_date` après génération d'une déclaration
 * **n'invalidait pas** la déclaration. Les contrats post-clôture
 * étaient silencieusement clippés, divergeant des montants du PDF
 * historique sans signal utilisateur. Risque légal en cas de litige.
 */
final class VehicleObserverDeclarationInvalidationTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Vehicle $vehicle;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        $this->vehicle = Vehicle::factory()->create([
            'current_status' => VehicleStatus::Active,
            'exit_date' => null,
            'exit_reason' => null,
        ]);
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    #[Test]
    public function pose_d_une_exit_date_invalide_les_declarations_avec_contrat_du_vehicule(): void
    {
        Contract::factory()->create([
            'company_id' => $this->company->id,
            'vehicle_id' => $this->vehicle->id,
            'start_date' => '2025-06-01',
            'end_date' => '2025-12-31',
            'contract_type' => ContractType::Lld,
        ]);
        $declaration = $this->makeDeclaration(2025);
        self::assertFalse($declaration->is_obsolete);

        // Pose une exit_date qui clôture les contrats post-15/11.
        $this->vehicle->update([
            'exit_date' => '2025-11-15',
            'exit_reason' => VehicleExitReason::Sold,
            'current_status' => VehicleStatus::Sold,
        ]);

        $fresh = $declaration->fresh();
        self::assertTrue($fresh->is_obsolete);
        self::assertSame(
            InvalidationReasonType::VehicleUpdated->value,
            $fresh->obsolete_reasons[0]['type'],
        );
        self::assertSame('vehicle', $fresh->obsolete_reasons[0]['entity']['type']);
        self::assertSame($this->vehicle->id, $fresh->obsolete_reasons[0]['entity']['id']);
        self::assertContains('exit_date', $fresh->obsolete_reasons[0]['fields_changed']);
    }

    #[Test]
    public function update_d_un_champ_non_fiscal_ne_declenche_pas_d_invalidation(): void
    {
        Contract::factory()->create([
            'company_id' => $this->company->id,
            'vehicle_id' => $this->vehicle->id,
            'start_date' => '2025-06-01',
            'end_date' => '2025-12-31',
            'contract_type' => ContractType::Lld,
        ]);
        $declaration = $this->makeDeclaration(2025);

        // Modifier uniquement le mileage (pas d'impact fiscal).
        $this->vehicle->update(['mileage_current' => 99999]);

        self::assertFalse($declaration->fresh()->is_obsolete);
    }

    #[Test]
    public function pose_d_une_exit_date_n_invalide_pas_si_aucun_contrat_du_vehicule(): void
    {
        // Déclaration sans aucun contrat sur ce véhicule.
        $declaration = $this->makeDeclaration(2025);

        $this->vehicle->update([
            'exit_date' => '2025-11-15',
            'exit_reason' => VehicleExitReason::Sold,
            'current_status' => VehicleStatus::Sold,
        ]);

        self::assertFalse($declaration->fresh()->is_obsolete);
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
