<?php

declare(strict_types=1);

namespace Tests\Feature\User\Dashboard;

use App\Models\Company;
use App\Models\Contract;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function dashboard_renvoie_les_stats_attendues(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
        ]);
        // Contrat 1 jour pour produire un cumul `contractDaysYear = 1`
        // (KPI brut côté Dashboard = nombre de jours-contrat occupés).
        $year = (int) config('floty.fiscal.available_years')[0];
        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => sprintf('%04d-06-15', $year),
            'end_date' => sprintf('%04d-06-15', $year),
        ]);

        $this->actingAs($user)
            ->get('/app/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/Dashboard/Index/Index')
                ->has('stats', fn (AssertableInertia $s) => $s
                    ->where('vehiclesCount', 1)
                    ->where('companiesCount', 1)
                    ->where('contractDaysYear', 1)
                    ->has('fiscalRulesCount')
                    ->has('totalTaxDue')),
            );
    }
}
