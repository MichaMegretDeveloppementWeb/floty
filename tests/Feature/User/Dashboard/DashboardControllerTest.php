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

        // Chantier perf Dashboard 2026-05-17 · les 4 props lourdes
        // sont deferred (Inertia::defer) · elles ne sont PAS présentes
        // dans la 1ère réponse Inertia, elles arrivent via 4 partial
        // reloads asynchrones déclenchés par les `<Deferred>` côté
        // front (4 vagues parallèles · KPIs fiscaux, recettes, history,
        // pendingTasks). Le payload initial est minimal · juste
        // `selectedYear` + `yearScope`.
        $this->actingAs($user)
            ->get('/app/dashboard?year='.$year)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/Dashboard/Index/Index')
                ->missing('kpis')
                ->missing('kpisRecettes')
                ->missing('history')
                ->missing('pendingTasks')
                ->has('selectedYear')
                ->has('yearScope'),
            );
    }
}
