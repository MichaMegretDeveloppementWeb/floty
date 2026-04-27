<?php

declare(strict_types=1);

namespace Tests\Feature\User\Assignment;

use App\Models\Assignment;
use App\Models\Company;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AssignmentControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function index_renvoie_les_options_vehicules_et_entreprises(): void
    {
        $user = User::factory()->create();
        Vehicle::factory()->count(2)->create();
        Company::factory()->count(3)->create();

        $this->actingAs($user)
            ->get('/app/assignments')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/Assignments/Index/Index')
                ->has('vehicles', 2)
                ->has('companies', 3),
            );
    }

    #[Test]
    public function vehicle_dates_renvoie_les_dates_occupees(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $company = Company::factory()->create();
        $year = (int) config('floty.fiscal.current_year');

        Assignment::factory()->create([
            'vehicle_id' => $vehicle->id,
            'company_id' => $company->id,
            'date' => "{$year}-05-12",
        ]);

        $this->actingAs($user)
            ->getJson("/app/assignments/vehicle-dates?vehicleId={$vehicle->id}&year={$year}")
            ->assertOk()
            ->assertJsonStructure(['vehicleBusyDates', 'pairDates'])
            ->assertJsonPath('vehicleBusyDates.0', "{$year}-05-12");
    }

    #[Test]
    public function vehicle_dates_rejette_un_id_invalide(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/app/assignments/vehicle-dates?vehicleId=0')
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'code'])
            ->assertJsonPath('code', 'InvalidQueryParameterException');
    }
}
