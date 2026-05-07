<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Planning;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use App\Services\Planning\PlanningHeatmapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PlanningHeatmapServiceTest extends TestCase
{
    use RefreshDatabase;

    private PlanningHeatmapService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(PlanningHeatmapService::class);
    }

    #[Test]
    public function build_heatmap_construit_la_matrice_52_semaines(): void
    {
        $year = 2024;
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);
        $company = Company::factory()->create();
        // Contrat 1 jour en semaine 10 (durée=1 → LCD ≤ 30 j, mais ce
        // test vérifie la heatmap brute (densité de jours occupés),
        // pas la fiscalité - donc l'exonération LCD n'invalide pas
        // l'assertion `weeks[9] = 1`).
        $weekStart = Carbon::now()->setISODate($year, 10)->startOfWeek();
        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => $weekStart->toDateString(),
            'end_date' => $weekStart->toDateString(),
        ]);

        $payload = $this->service->buildHeatmap($year);

        $vehicles = $payload['vehicles']->toArray();
        self::assertCount(1, $vehicles);
        self::assertCount(52, $vehicles[0]['weeks']);
        self::assertSame(1, $vehicles[0]['weeks'][9]); // semaine 10 indexée [9]
        self::assertSame(1, $vehicles[0]['daysTotal']);

        $companies = $payload['companies']->toArray();
        self::assertCount(1, $companies);
    }

    #[Test]
    public function build_heatmap_for_company_filtre_les_jours_par_entreprise(): void
    {
        // 1 véhicule, 2 entreprises, 1 contrat sur des semaines
        // distinctes pour chaque. La cellule chiffre doit refléter
        // uniquement la company demandée ; la cellule couleur (densité
        // globale) doit refléter les 2.
        $year = 2024;
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $weekA = Carbon::now()->setISODate($year, 10)->startOfWeek();
        Contract::factory()->forVehicle($vehicle)->forCompany($companyA)->create([
            'start_date' => $weekA->toDateString(),
            'end_date' => $weekA->toDateString(),
        ]);

        $weekB = Carbon::now()->setISODate($year, 20)->startOfWeek();
        Contract::factory()->forVehicle($vehicle)->forCompany($companyB)->create([
            'start_date' => $weekB->toDateString(),
            'end_date' => $weekB->toDateString(),
        ]);

        $payload = $this->service->buildHeatmapForCompany($year, $companyA);

        $vehicleRow = $payload['vehicles']->toArray()[0];

        // weeksForCompany ne reflète que companyA (semaine 10).
        self::assertSame(1, $vehicleRow['weeksForCompany'][9]);
        self::assertSame(0, $vehicleRow['weeksForCompany'][19]);

        // weeksGlobal reflète les deux contrats (semaines 10 et 20).
        self::assertSame(1, $vehicleRow['weeksGlobal'][9]);
        self::assertSame(1, $vehicleRow['weeksGlobal'][19]);

        // daysTotalForCompany compte uniquement companyA.
        self::assertSame(1, $vehicleRow['daysTotalForCompany']);

        // Métadonnées : la company sélectionnée et la liste de toutes
        // les companies pour le sélecteur en haut de la vue.
        self::assertSame($companyA->id, $payload['company']->id);
        self::assertCount(2, $payload['companies']->toArray());
    }

    #[Test]
    public function build_heatmap_for_company_compte_taxe_uniquement_pour_cette_entreprise(): void
    {
        // 1 véhicule, 2 entreprises, 2 contrats taxables (>30 jours
        // chacun pour sortir de l'exonération LCD R-2024-021). La
        // taxe annuelle pour companyA ne doit refléter que sa part.
        $year = 2024;
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $startA = Carbon::create($year, 1, 15);
        Contract::factory()->forVehicle($vehicle)->forCompany($companyA)->create([
            'start_date' => $startA->toDateString(),
            'end_date' => $startA->copy()->addDays(40)->toDateString(),
        ]);

        $startB = Carbon::create($year, 6, 1);
        Contract::factory()->forVehicle($vehicle)->forCompany($companyB)->create([
            'start_date' => $startB->toDateString(),
            'end_date' => $startB->copy()->addDays(40)->toDateString(),
        ]);

        $payloadA = $this->service->buildHeatmapForCompany($year, $companyA);
        $payloadB = $this->service->buildHeatmapForCompany($year, $companyB);

        $taxA = $payloadA['vehicles']->toArray()[0]['annualTaxDueForCompany'];
        $taxB = $payloadB['vehicles']->toArray()[0]['annualTaxDueForCompany'];

        // Les taxes pour A et B sont chacune > 0, et chacune
        // strictement inférieure au total des deux (pas de
        // double-comptage, pas de mélange).
        self::assertGreaterThan(0.0, $taxA);
        self::assertGreaterThan(0.0, $taxB);
        self::assertEqualsWithDelta($taxA, $taxB, 0.5); // contrats symétriques sur l'année 2024
    }
}
