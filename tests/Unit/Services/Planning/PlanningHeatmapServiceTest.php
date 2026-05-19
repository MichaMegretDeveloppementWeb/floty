<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Planning;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use App\Services\Fiscal\FleetFiscalAggregator;
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
    public function build_heatmap_construit_la_matrice_53_cellules(): void
    {
        // SC16 (2026-05-18) : la heatmap a désormais TOUJOURS 53 cellules
        // (au lieu de 52 ou 53 selon l'année) pour couvrir TOUTES les
        // semaines ISO contenant au moins 1 jour de l'année. Pour 2024
        // (1er jan = lundi, sem ISO 1 = lun 1/1 - dim 7/1), la cellule 10
        // correspond à la semaine ISO 10 = lun 4/3 - dim 10/3/2024.
        $year = 2024;
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);
        $company = Company::factory()->create();
        // Contrat 1 jour en cellule 10 (lun 4/3/2024 = semaine ISO 10).
        $weekStart = Carbon::now()->setISODate($year, 10)->startOfWeek();
        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => $weekStart->toDateString(),
            'end_date' => $weekStart->toDateString(),
        ]);

        $payload = $this->service->buildHeatmap($year);

        $vehicles = $payload['vehicles']->toArray();
        self::assertCount(1, $vehicles);
        self::assertCount(53, $vehicles[0]['weeks']);
        self::assertSame(1, $vehicles[0]['weeks'][9]); // cellule 10 indexée [9]
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
    public function real_costs_for_vehicles_compte_taxe_uniquement_pour_l_entreprise_scopee(): void
    {
        // Chantier perf Étape 3 (2026-05-17) : `realCostsForVehicles`
        // scopée à companyA ne doit refléter que la part de companyA,
        // pas celle de companyB : sémantique strictement équivalente
        // à l'ancien `annualTaxDueForCompany`.
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

        $costsA = $this->service->realCostsForVehicles($year, $companyA->id);
        $costsB = $this->service->realCostsForVehicles($year, $companyB->id);

        $taxA = $costsA[$vehicle->id]->annualTaxDue;
        $taxB = $costsB[$vehicle->id]->annualTaxDue;

        self::assertGreaterThan(0.0, $taxA);
        self::assertGreaterThan(0.0, $taxB);
        self::assertEqualsWithDelta($taxA, $taxB, 0.5); // contrats symétriques sur l'année 2024
    }

    #[Test]
    public function real_costs_for_vehicles_global_somme_les_deux_entreprises(): void
    {
        // Sans `companyId`, `realCostsForVehicles` calcule la taxe
        // annuelle globale = somme des parts toutes entreprises.
        // Garantit que le scope global ≠ scope entreprise.
        //
        // Précondition cache : `vehicleAnnualTax` mémoïsé par
        // `{vehicleId}|{year}` sans considérer le scope : on force un
        // container neuf entre chaque appel pour simuler 2 requêtes
        // Inertia::defer indépendantes (cas réel d'usage en prod).
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

        $taxGlobal = $this->freshService()->realCostsForVehicles($year)[$vehicle->id]->annualTaxDue;
        $taxA = $this->freshService()->realCostsForVehicles($year, $companyA->id)[$vehicle->id]->annualTaxDue;
        $taxB = $this->freshService()->realCostsForVehicles($year, $companyB->id)[$vehicle->id]->annualTaxDue;

        self::assertGreaterThan($taxA, $taxGlobal);
        self::assertGreaterThan($taxB, $taxGlobal);
    }

    #[Test]
    public function full_year_costs_for_vehicles_independant_du_scope(): void
    {
        // Chantier perf Étape 3 : `fullYearCostsForVehicles` est
        // toujours indépendant du scope entreprise : valeurs théoriques
        // 100 % usage. Aucun param companyId : cohérent avec l'UI où
        // la « Taxe pleine » est affichée à l'identique en Vue
        // d'ensemble et en Vue Entreprise.
        $year = 2024;
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);

        $costs = $this->service->fullYearCostsForVehicles($year);

        self::assertArrayHasKey($vehicle->id, $costs);
        self::assertGreaterThan(0.0, $costs[$vehicle->id]->fullYearTax);
        self::assertGreaterThan(0.0, $costs[$vehicle->id]->dailyTaxRate);
    }

    private function freshService(): PlanningHeatmapService
    {
        $this->app->forgetInstance(FleetFiscalAggregator::class);
        $this->app->forgetInstance(PlanningHeatmapService::class);

        return $this->app->make(PlanningHeatmapService::class);
    }
}
