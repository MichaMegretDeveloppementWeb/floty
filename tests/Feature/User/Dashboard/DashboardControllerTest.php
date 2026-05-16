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

        // S2.3 (plan optim perf 2026-05-15) · `history` et `pendingTasks`
        // sont deferred (Inertia::defer) · ils ne sont PAS présents dans
        // la 1ère réponse Inertia, ils arrivent via une 2e requête
        // asynchrone partial reload déclenchée par `<Deferred>` côté
        // front. Le test assert l'absence au mount initial · suffisant
        // pour prouver que le defer est correctement câblé (le
        // comportement de la 2e requête est testé par Inertia lui-même).
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
                    ->has('recettesLocativesCents')
                    ->has('previousYearComparison'))
                ->missing('history')
                ->missing('pendingTasks')
                ->has('selectedYear')
                ->has('yearScope'),
            );
    }
}
