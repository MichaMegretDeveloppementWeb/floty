<?php

declare(strict_types=1);

namespace Tests\Feature\User\Vehicle;

use App\Enums\Unavailability\UnavailabilityType;
use App\Enums\Vehicle\VehicleExitReason;
use App\Enums\Vehicle\VehicleStatus;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Unavailability;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests pour le cycle de vie véhicule (sortie de flotte +
 * réactivation), cf. ADR-0018 + chantier E.3.
 *
 * Couvre :
 *   - Sortie sans conflits → DB updated + status mappé + toast-success
 *   - Sortie avec conflits → bloquée par exception + toast-error +
 *     véhicule inchangé
 *   - Réactivation → exit_date/reason NULL + status active
 *   - Cycle exit → reactivate → exit (idempotence)
 *   - Validation période sur Contract via AvailableForPeriod (422)
 */
final class ExitVehicleControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function exit_sans_conflits_pose_les_colonnes_et_mappe_le_status(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create([
            'exit_date' => null,
            'current_status' => VehicleStatus::Active,
        ]);

        $this->actingAs($user)
            ->post("/app/vehicles/{$vehicle->id}/exit", [
                'exit_date' => '2025-06-15',
                'exit_reason' => 'sold',
                'note' => 'Vendu au garage Dupont.',
            ])
            ->assertRedirect("/app/vehicles/{$vehicle->id}")
            ->assertSessionHas('toast-success');

        $vehicle->refresh();
        self::assertSame('2025-06-15', $vehicle->exit_date->toDateString());
        self::assertSame(VehicleExitReason::Sold, $vehicle->exit_reason);
        self::assertSame(VehicleStatus::Sold, $vehicle->current_status);
    }

    #[Test]
    public function exit_avec_motif_transferred_mappe_status_other(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create([
            'exit_date' => null,
            'current_status' => VehicleStatus::Active,
        ]);

        $this->actingAs($user)
            ->post("/app/vehicles/{$vehicle->id}/exit", [
                'exit_date' => '2025-06-15',
                'exit_reason' => 'transferred',
                'note' => null,
            ])
            ->assertRedirect();

        $vehicle->refresh();
        self::assertSame(VehicleExitReason::Transferred, $vehicle->exit_reason);
        // Asymétrie acceptée : current_status n'a pas de case dédiée pour
        // transferred / stolen_unrecovered → mappé à Other (cf. ADR-0018).
        self::assertSame(VehicleStatus::Other, $vehicle->current_status);
    }

    #[Test]
    public function exit_avec_contrat_qui_deborde_est_bloque(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create(['exit_date' => null]);
        $company = Company::factory()->create();

        Contract::factory()->create([
            'vehicle_id' => $vehicle->id,
            'company_id' => $company->id,
            'start_date' => '2025-05-01',
            'end_date' => '2025-07-31',
        ]);

        $this->actingAs($user)
            ->post("/app/vehicles/{$vehicle->id}/exit", [
                'exit_date' => '2025-06-15',
                'exit_reason' => 'sold',
                'note' => null,
            ])
            ->assertRedirect()
            ->assertSessionHas('toast-error');

        $vehicle->refresh();
        self::assertNull($vehicle->exit_date);
        self::assertNull($vehicle->exit_reason);
    }

    #[Test]
    public function exit_avec_indispo_qui_deborde_est_bloque(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create(['exit_date' => null]);

        Unavailability::factory()->create([
            'vehicle_id' => $vehicle->id,
            'type' => UnavailabilityType::Maintenance,
            'has_fiscal_impact' => false,
            'start_date' => '2025-06-10',
            'end_date' => '2025-07-05',
        ]);

        $this->actingAs($user)
            ->post("/app/vehicles/{$vehicle->id}/exit", [
                'exit_date' => '2025-06-15',
                'exit_reason' => 'sold',
                'note' => null,
            ])
            ->assertRedirect()
            ->assertSessionHas('toast-error');

        $vehicle->refresh();
        self::assertNull($vehicle->exit_date);
    }

    #[Test]
    public function reactivate_efface_exit_date_et_reason_et_remet_status_active(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create([
            'exit_date' => '2025-06-15',
            'exit_reason' => VehicleExitReason::Sold,
            'current_status' => VehicleStatus::Sold,
        ]);

        $this->actingAs($user)
            ->post("/app/vehicles/{$vehicle->id}/reactivate")
            ->assertRedirect("/app/vehicles/{$vehicle->id}")
            ->assertSessionHas('toast-success');

        $vehicle->refresh();
        self::assertNull($vehicle->exit_date);
        self::assertNull($vehicle->exit_reason);
        self::assertSame(VehicleStatus::Active, $vehicle->current_status);
    }

    #[Test]
    public function cycle_exit_puis_reactivate_puis_exit_a_nouveau(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create(['exit_date' => null]);

        // 1ʳᵉ sortie
        $this->actingAs($user)
            ->post("/app/vehicles/{$vehicle->id}/exit", [
                'exit_date' => '2025-06-15',
                'exit_reason' => 'sold',
                'note' => null,
            ])
            ->assertRedirect();

        $vehicle->refresh();
        self::assertNotNull($vehicle->exit_date);

        // Réactivation
        $this->actingAs($user)
            ->post("/app/vehicles/{$vehicle->id}/reactivate")
            ->assertRedirect();

        $vehicle->refresh();
        self::assertNull($vehicle->exit_date);

        // 2e sortie avec un autre motif
        $this->actingAs($user)
            ->post("/app/vehicles/{$vehicle->id}/exit", [
                'exit_date' => '2025-08-01',
                'exit_reason' => 'destroyed',
                'note' => 'Destruction VHU.',
            ])
            ->assertRedirect();

        $vehicle->refresh();
        self::assertSame('2025-08-01', $vehicle->exit_date->toDateString());
        self::assertSame(VehicleExitReason::Destroyed, $vehicle->exit_reason);
        self::assertSame(VehicleStatus::Destroyed, $vehicle->current_status);
    }

    #[Test]
    public function creation_contrat_sur_vehicule_sorti_apres_exit_date_renvoie_422(): void
    {
        // Cf. ADR-0018 § 5 + chantier E.2 - la rule AvailableForPeriod
        // est attachée à StoreContractData et bloque toute saisie qui
        // chevauche/dépasse exit_date avec un message FR explicite.
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create([
            'exit_date' => '2025-06-15',
            'exit_reason' => VehicleExitReason::Sold,
            'current_status' => VehicleStatus::Sold,
        ]);
        $company = Company::factory()->create();

        $this->actingAs($user)
            ->post('/app/contracts', [
                'vehicle_id' => $vehicle->id,
                'company_id' => $company->id,
                'driver_id' => null,
                'start_date' => '2025-07-01',
                'end_date' => '2025-07-31',
                'contract_reference' => null,
                'notes' => null,
            ])
            ->assertSessionHasErrors('end_date');
    }

    #[Test]
    public function index_par_defaut_masque_les_vehicules_retires(): void
    {
        $user = User::factory()->create();
        $active = Vehicle::factory()->create(['exit_date' => null]);
        $exited = Vehicle::factory()->create([
            'exit_date' => '2025-06-15',
            'exit_reason' => VehicleExitReason::Sold,
            'current_status' => VehicleStatus::Sold,
        ]);
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $active->id]);
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $exited->id]);

        $this->actingAs($user)
            ->get('/app/vehicles')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/Vehicles/Index/Index')
                ->where('query.includeExited', false)
                ->has('vehicles.data', 1, fn (AssertableInertia $v) => $v
                    ->where('id', $active->id)
                    ->etc()),
            );
    }

    #[Test]
    public function index_avec_include_exited_remonte_les_deux(): void
    {
        $user = User::factory()->create();
        $active = Vehicle::factory()->create(['exit_date' => null]);
        $exited = Vehicle::factory()->create([
            'exit_date' => '2025-06-15',
            'exit_reason' => VehicleExitReason::Sold,
            'current_status' => VehicleStatus::Sold,
        ]);
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $active->id]);
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $exited->id]);

        // Note : la query string est camelCase (`includeExited`) côté
        // ADR-0020, contrairement à l'ancien `include_exited` snake_case.
        $this->actingAs($user)
            ->get('/app/vehicles?includeExited=1')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('query.includeExited', true)
                ->has('vehicles.data', 2),
            );
    }

    #[Test]
    public function show_d_un_vehicule_retire_reste_accessible(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create([
            'exit_date' => '2025-06-15',
            'exit_reason' => VehicleExitReason::Sold,
            'current_status' => VehicleStatus::Sold,
        ]);
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);

        $this->actingAs($user)
            ->get("/app/vehicles/{$vehicle->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/Vehicles/Show/Index')
                ->has('vehicle', fn (AssertableInertia $v) => $v
                    ->where('id', $vehicle->id)
                    ->where('isExited', true)
                    ->where('exitDate', '2025-06-15')
                    ->where('exitReason', 'sold')
                    ->etc()),
            );
    }

    #[Test]
    public function heatmap_planning_exclut_les_vehicules_sortis_avant_le_1er_janvier_de_l_annee(): void
    {
        $user = User::factory()->create();
        $year = (int) config('floty.fiscal.available_years')[0];

        // Sorti l'année précédente → ne doit pas apparaître.
        $vehiclePrev = Vehicle::factory()->create([
            'exit_date' => sprintf('%d-12-31', $year - 1),
            'exit_reason' => VehicleExitReason::Sold,
            'current_status' => VehicleStatus::Sold,
        ]);
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehiclePrev->id]);

        // Sorti dans l'année cible → doit apparaître.
        $vehicleMidYear = Vehicle::factory()->create([
            'exit_date' => sprintf('%d-06-15', $year),
            'exit_reason' => VehicleExitReason::Sold,
            'current_status' => VehicleStatus::Sold,
        ]);
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicleMidYear->id]);

        $this->actingAs($user)
            ->get('/app/planning')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where(
                    'vehicles',
                    function (mixed $vehicles) use ($vehicleMidYear, $vehiclePrev): bool {
                        $ids = collect($vehicles)->pluck('id')->all();

                        return in_array($vehicleMidYear->id, $ids, true)
                            && ! in_array($vehiclePrev->id, $ids, true);
                    },
                )
                ->etc(),
            );
    }
}
