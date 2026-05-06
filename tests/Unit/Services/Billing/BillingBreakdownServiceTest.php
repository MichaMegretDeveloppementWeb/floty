<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Billing;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Vehicle;
use App\Models\VehicleYearlyPricing;
use App\Services\Billing\BillingBreakdownService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests d'intégration du BillingBreakdownService (Phase 14.D V1.2).
 *
 * Tarifs constants : 90 € / 500 € / 1 800 € (cents : 9 000 / 50 000 / 180 000).
 */
final class BillingBreakdownServiceTest extends TestCase
{
    use RefreshDatabase;

    private const int DAILY = 9_000;

    private const int WEEKLY = 50_000;

    private const int MONTHLY = 180_000;

    private BillingBreakdownService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(BillingBreakdownService::class);
    }

    #[Test]
    public function company_breakdown_renvoie_toujours_12_entrees_dans_l_ordre(): void
    {
        $company = Company::factory()->create();

        $result = $this->service->byCompanyForYear($company->id, 2024);

        $this->assertCount(12, $result->entries);
        for ($i = 0; $i < 12; $i++) {
            $this->assertSame($i + 1, $result->entries[$i]->month);
        }
        $this->assertSame(2024, $result->year);
        $this->assertSame(0, $result->yearTotalDaysUsed);
        $this->assertSame(0, $result->yearTotalCents);
        $this->assertFalse($result->hasAnyMissingPricing);
    }

    #[Test]
    public function company_breakdown_aggregate_les_mois_factures(): void
    {
        $company = Company::factory()->create();
        $vehicle = Vehicle::factory()->create(['license_plate' => 'AA-001-AA']);
        VehicleYearlyPricing::factory()->for($vehicle)->forYear(2024)
            ->withRates(self::DAILY, self::WEEKLY, self::MONTHLY)->create();

        // Janvier : mois plein. Mars : 10 jours. Décembre : 1 jour.
        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => '2024-01-01', 'end_date' => '2024-01-31',
        ]);
        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => '2024-03-01', 'end_date' => '2024-03-10',
        ]);
        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => '2024-12-15', 'end_date' => '2024-12-15',
        ]);

        $result = $this->service->byCompanyForYear($company->id, 2024);

        // Janvier : 31 jours → 1 mois + 1 jour = 189 000.
        $this->assertSame(31, $result->entries[0]->daysUsed);
        $this->assertSame(189_000, $result->entries[0]->totalCents);

        // Février : pas de contrat.
        $this->assertSame(0, $result->entries[1]->daysUsed);
        $this->assertSame(0, $result->entries[1]->totalCents);

        // Mars : 10 jours → 1 sem + 3 jours = 77 000.
        $this->assertSame(10, $result->entries[2]->daysUsed);
        $this->assertSame(77_000, $result->entries[2]->totalCents);

        // Décembre : 1 jour = 9 000.
        $this->assertSame(1, $result->entries[11]->daysUsed);
        $this->assertSame(9_000, $result->entries[11]->totalCents);

        $this->assertSame(31 + 10 + 1, $result->yearTotalDaysUsed);
        $this->assertSame(189_000 + 77_000 + 9_000, $result->yearTotalCents);
        $this->assertFalse($result->hasAnyMissingPricing);
    }

    #[Test]
    public function company_breakdown_marque_les_mois_avec_tarif_manquant_sans_interrompre(): void
    {
        $company = Company::factory()->create();
        $v1 = Vehicle::factory()->create(['license_plate' => 'AA-111-AA']);
        $v2 = Vehicle::factory()->create(['license_plate' => 'BB-222-BB']);
        // Tarif sur v1 seulement.
        VehicleYearlyPricing::factory()->for($v1)->forYear(2024)
            ->withRates(self::DAILY, self::WEEKLY, self::MONTHLY)->create();

        // Mars : v1 seul (calcul OK).
        Contract::factory()->forVehicle($v1)->forCompany($company)->create([
            'start_date' => '2024-03-01', 'end_date' => '2024-03-10',
        ]);
        // Mai : v2 (sans tarif → mois marqué missing).
        Contract::factory()->forVehicle($v2)->forCompany($company)->create([
            'start_date' => '2024-05-01', 'end_date' => '2024-05-15',
        ]);

        $result = $this->service->byCompanyForYear($company->id, 2024);

        // Mars OK.
        $this->assertFalse($result->entries[2]->hasMissingPricing);
        $this->assertSame(77_000, $result->entries[2]->totalCents);

        // Mai bloqué.
        $this->assertTrue($result->entries[4]->hasMissingPricing);
        $this->assertNull($result->entries[4]->totalCents);
        $this->assertSame(0, $result->entries[4]->daysUsed); // Conservé à 0 pour ne pas tromper.

        // Total annuel marqué null (au moins 1 mois bloqué).
        $this->assertTrue($result->hasAnyMissingPricing);
        $this->assertNull($result->yearTotalCents);
    }

    #[Test]
    public function vehicle_breakdown_somme_recettes_cross_entreprises(): void
    {
        // Un véhicule loué à 2 entreprises distinctes le même mois :
        // chaque entreprise est facturée séparément.
        $vehicle = Vehicle::factory()->create(['license_plate' => 'AA-001-AA']);
        VehicleYearlyPricing::factory()->for($vehicle)->forYear(2024)
            ->withRates(self::DAILY, self::WEEKLY, self::MONTHLY)->create();

        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        // Mars : v1 chez A pendant 10 jours (= 77 000), chez B pendant
        // 5 jours (= 45 000). Total mars = 122 000.
        Contract::factory()->forVehicle($vehicle)->forCompany($companyA)->create([
            'start_date' => '2024-03-01', 'end_date' => '2024-03-10',
        ]);
        Contract::factory()->forVehicle($vehicle)->forCompany($companyB)->create([
            'start_date' => '2024-03-15', 'end_date' => '2024-03-19',
        ]);

        $result = $this->service->byVehicleForYear($vehicle->id, 2024);

        // Mars.
        $this->assertSame(15, $result->entries[2]->daysUsed); // 10 + 5.
        $this->assertSame(122_000, $result->entries[2]->totalCents); // 77 000 + 45 000.
        $this->assertFalse($result->entries[2]->hasMissingPricing);
    }

    #[Test]
    public function vehicle_breakdown_marque_l_annee_entiere_si_le_tarif_annuel_manque(): void
    {
        $vehicle = Vehicle::factory()->create(['license_plate' => 'AA-001-AA']);
        $company = Company::factory()->create();
        // Pas de tarif 2024 sur ce véhicule.

        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => '2024-03-01', 'end_date' => '2024-03-10',
        ]);

        $result = $this->service->byVehicleForYear($vehicle->id, 2024);

        // Le mois où le véhicule est utilisé est marqué missing.
        $this->assertTrue($result->entries[2]->hasMissingPricing);
        $this->assertNull($result->entries[2]->totalCents);

        // Les autres mois sans contrat ne le sont pas (la branche
        // « contrats vide » court-circuite avant le check du tarif).
        $this->assertFalse($result->entries[0]->hasMissingPricing);
        $this->assertSame(0, $result->entries[0]->totalCents);

        $this->assertTrue($result->hasAnyMissingPricing);
        $this->assertNull($result->yearTotalCents);
    }

    #[Test]
    public function vehicle_breakdown_total_jours_et_cents_quand_tout_est_renseigne(): void
    {
        $vehicle = Vehicle::factory()->create(['license_plate' => 'AA-001-AA']);
        VehicleYearlyPricing::factory()->for($vehicle)->forYear(2024)
            ->withRates(self::DAILY, self::WEEKLY, self::MONTHLY)->create();
        $company = Company::factory()->create();

        // 7 jours en mai = 1 sem = 50 000.
        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => '2024-05-01', 'end_date' => '2024-05-07',
        ]);

        $result = $this->service->byVehicleForYear($vehicle->id, 2024);

        $this->assertSame(7, $result->yearTotalDaysUsed);
        $this->assertSame(50_000, $result->yearTotalCents);
        $this->assertFalse($result->hasAnyMissingPricing);
    }
}
