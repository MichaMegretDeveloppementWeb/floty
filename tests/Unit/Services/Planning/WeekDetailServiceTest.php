<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Planning;

use App\Data\User\Planning\PreviewTaxesInputData;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use App\Services\Planning\WeekDetailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class WeekDetailServiceTest extends TestCase
{
    use RefreshDatabase;

    private WeekDetailService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(WeekDetailService::class);
    }

    #[Test]
    public function build_week_renvoie_7_jours_avec_contrats_eager_loaded(): void
    {
        $year = 2024;
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);
        $company = Company::factory()->create();
        // Contrat couvrant le lundi de la semaine 8 (1 jour).
        $weekStart = Carbon::now()->setISODate($year, 8)->startOfWeek();
        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => $weekStart->toDateString(),
            'end_date' => $weekStart->toDateString(),
        ]);

        $week = $this->service->buildWeek($vehicle->id, 8, $year);

        self::assertCount(7, $week->days);
        self::assertNotNull($week->days[0]->contract);
        self::assertNull($week->days[1]->contract);
        self::assertCount(1, $week->companiesOnWeek);
        self::assertSame(1, $week->companiesOnWeek[0]->days);
    }

    #[Test]
    public function build_week_for_company_anonymise_les_contrats_des_autres_entreprises(): void
    {
        // Vue Entreprise (chantier P3) : quand le drawer est ouvert
        // depuis la Vue Entreprise (donc avec un companyId), les
        // contrats d'autres entreprises sont anonymisés. Le frontend
        // ne reçoit jamais l'identité/couleur de l'entreprise
        // occupante.
        $year = 2024;
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        // Semaine 10 : lundi pour companyA, mercredi pour companyB.
        $weekStart = Carbon::now()->setISODate($year, 10)->startOfWeek();
        $monday = $weekStart->toDateString();
        $wednesday = $weekStart->copy()->addDays(2)->toDateString();

        Contract::factory()->forVehicle($vehicle)->forCompany($companyA)->create([
            'start_date' => $monday,
            'end_date' => $monday,
        ]);
        Contract::factory()->forVehicle($vehicle)->forCompany($companyB)->create([
            'start_date' => $wednesday,
            'end_date' => $wednesday,
        ]);

        $week = $this->service->buildWeekForCompany($vehicle->id, 10, $year, $companyA->id);

        // Lundi : contrat companyA visible (pas anonyme).
        $mondaySlot = $week->days[0];
        self::assertNotNull($mondaySlot->contract);
        self::assertSame($companyA->id, $mondaySlot->contract->company->id);
        self::assertFalse($mondaySlot->isOccupiedByOther);

        // Mardi : libre.
        $tuesdaySlot = $week->days[1];
        self::assertNull($tuesdaySlot->contract);
        self::assertFalse($tuesdaySlot->isOccupiedByOther);

        // Mercredi : contrat companyB anonymisé.
        $wednesdaySlot = $week->days[2];
        self::assertNull($wednesdaySlot->contract);
        self::assertTrue($wednesdaySlot->isOccupiedByOther);

        // companiesOnWeek ne contient que companyA.
        self::assertCount(1, $week->companiesOnWeek);
        self::assertSame($companyA->id, $week->companiesOnWeek[0]->company->id);
    }

    #[Test]
    public function preview_taxes_renvoie_le_cout_standalone_du_contrat(): void
    {
        // Sémantique : LCD/LLD se calcule par contrat individuellement
        // (pas de cumul annuel). Le preview ignore tout autre contrat
        // existant pour le même couple véhicule × entreprise.
        $year = 2024;
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);
        $company = Company::factory()->create();

        // Plage 35 j (>30 → LLD) : coût taxable.
        $start = Carbon::create($year, 7, 15);
        $dates = [
            $start->toDateString(),
            $start->copy()->addDays(34)->toDateString(),
        ];

        $preview = $this->service->previewTaxes(
            new PreviewTaxesInputData(
                vehicleId: $vehicle->id,
                companyId: $company->id,
                dates: $dates,
            ),
            $year,
        );

        self::assertSame($year, $preview->fiscalYear);
        self::assertSame(35, $preview->daysCount);
        self::assertGreaterThan(0.0, $preview->breakdown->totalDue);
    }

    #[Test]
    public function preview_taxes_signale_une_annee_sans_regles_fiscales(): void
    {
        // Le registre fiscal couvre 2024-2026. Pour 2027 (sans règles
        // codées), previewTaxes renvoie un payload neutre (supported=false,
        // breakdown=null) au lieu de lever FiscalCalculationException · le
        // wizard affiche "Pas de règles fiscales" sans toast d'erreur.
        $year = 2027;
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2020-01-01',
            'effective_to' => null,
        ]);
        $company = Company::factory()->create();

        $start = Carbon::create($year, 7, 15);
        $preview = $this->service->previewTaxes(
            new PreviewTaxesInputData(
                vehicleId: $vehicle->id,
                companyId: $company->id,
                dates: [
                    $start->toDateString(),
                    $start->copy()->addDays(34)->toDateString(),
                ],
            ),
            $year,
        );

        self::assertFalse($preview->supported);
        self::assertNull($preview->breakdown);
        self::assertSame(35, $preview->daysCount);
    }

    #[Test]
    public function preview_taxes_ignore_les_autres_contrats_du_couple(): void
    {
        // Régression : avant le passage au calcul standalone, le service
        // calculait un delta vs cumul existant : un contrat préexistant
        // pour le même couple déformait le résultat. Désormais le
        // preview est strictement standalone.
        $year = 2024;
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);
        $company = Company::factory()->create();

        // Contrat préexistant 35 j sur ce couple.
        $start = Carbon::create($year, 7, 15);
        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addDays(34)->toDateString(),
        ]);

        // Preview pour les MÊMES dates : sans cumul, le résultat doit
        // refléter le coût standalone du contrat (35 j, taxe non nulle).
        $sameDates = [
            $start->toDateString(),
            $start->copy()->addDays(34)->toDateString(),
        ];

        $preview = $this->service->previewTaxes(
            new PreviewTaxesInputData(
                vehicleId: $vehicle->id,
                companyId: $company->id,
                dates: $sameDates,
            ),
            $year,
        );

        self::assertSame(35, $preview->daysCount);
        self::assertGreaterThan(0.0, $preview->breakdown->totalDue);
    }
}
