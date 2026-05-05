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
    public function dashboard_expose_les_4_blocs_doctrinaux(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
        ]);
        // Contrat 1 jour pour produire un cumul `joursVehicule` non nul.
        $year = 2024;
        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => sprintf('%04d-06-15', $year),
            'end_date' => sprintf('%04d-06-15', $year),
        ]);

        $this->actingAs($user)
            ->get('/app/dashboard?year='.$year)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/Dashboard/Index/Index')
                ->has('kpis', fn (AssertableInertia $k) => $k
                    ->has('year')
                    ->has('joursVehicule')
                    ->has('contracts')
                    ->has('contractsActiveNow')
                    ->has('taxesDues')
                    ->has('tauxOccupation')
                    ->has('previousYearComparison'))
                ->has('history')
                ->has('activity', fn (AssertableInertia $a) => $a
                    ->has('last30DaysHeatmap')
                    ->has('topExpensiveVehicles'))
                ->has('pendingTasks', fn (AssertableInertia $t) => $t
                    ->where('pendingDeclarations', 0)
                    ->where('pendingInvoices', 0))
                ->has('selectedYear')
                ->has('yearScope'),
            );
    }
}
