<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Invoice;

use App\Actions\Invoice\BulkGenerateInvoicesAction;
use App\Models\Company;
use App\Models\Contract;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleYearlyPricing;
use App\Services\Billing\BillingBreakdownService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Données réelles plutôt que mocks : valide la chaîne complète
 * Bulk → Resolver → Generate → Invoice + PDF.
 */
final class BulkGenerateInvoicesActionTest extends TestCase
{
    use RefreshDatabase;

    private BulkGenerateInvoicesAction $action;

    private const array ISSUER = [
        'name' => 'Loueur Test',
        'addressLine1' => '12 rue du Test',
        'addressLine2' => null,
        'postalCode' => '75001',
        'city' => 'Paris',
        'siren' => '123456789',
        'contactEmail' => 'test@loueur.fr',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->action = $this->app->make(BulkGenerateInvoicesAction::class);
    }

    #[Test]
    public function genere_toutes_les_annexes_en_attente_en_ordre_chronologique(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::create(2025, 6, 1));
        try {
            [$user, $company, $vehicle] = $this->seedCompanyVehiclePricing(2024);

            Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
                'start_date' => '2024-01-05', 'end_date' => '2024-01-10',
            ]);
            Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
                'start_date' => '2024-04-02', 'end_date' => '2024-04-08',
            ]);
            Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
                'start_date' => '2024-07-15', 'end_date' => '2024-07-22',
            ]);

            $report = $this->action->execute(
                companyId: $company->id,
                year: 2024,
                generatedByUserId: $user->id,
                issuer: self::ISSUER,
            );

            $this->assertCount(3, $report->generated);
            $this->assertCount(0, $report->failed);
            $this->assertSame($company->id, $report->companyId);
            $this->assertSame(2024, $report->year);

            $this->assertSame([1, 4, 7], array_map(
                static fn ($g) => $g->month,
                $report->generated,
            ));

            // Numéro `YYYY-MM-NNNN` séquentiel par couple (year, month).
            $this->assertSame('2024-01-0001', $report->generated[0]->invoiceNumber);
            $this->assertSame('2024-04-0001', $report->generated[1]->invoiceNumber);
            $this->assertSame('2024-07-0001', $report->generated[2]->invoiceNumber);

            $this->assertDatabaseCount('invoices', 3);
            $this->assertDatabaseCount('invoice_lines', 3);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    #[Test]
    public function continue_la_sequence_quand_un_mois_echoue_par_tarif_manquant(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::create(2025, 6, 1));
        try {
            [$user, $company, $vehicleWithPricing] = $this->seedCompanyVehiclePricing(2024);

            // Second véhicule SANS tarif 2024 : Mars sera filtré par
            // le resolver (pré-filtre missing pricing).
            $vehicleNoPricing = Vehicle::factory()->create();

            Contract::factory()->forVehicle($vehicleWithPricing)->forCompany($company)->create([
                'start_date' => '2024-01-10', 'end_date' => '2024-01-14',
            ]);
            Contract::factory()->forVehicle($vehicleNoPricing)->forCompany($company)->create([
                'start_date' => '2024-03-05', 'end_date' => '2024-03-08',
            ]);
            Contract::factory()->forVehicle($vehicleWithPricing)->forCompany($company)->create([
                'start_date' => '2024-05-02', 'end_date' => '2024-05-04',
            ]);

            $report = $this->action->execute(
                companyId: $company->id,
                year: 2024,
                generatedByUserId: $user->id,
                issuer: self::ISSUER,
            );

            // Resolver pré-filtre Mars (missing pricing) : 2 generated, 0 failed.
            $this->assertCount(2, $report->generated);
            $this->assertCount(0, $report->failed);
            $this->assertSame([1, 5], array_map(
                static fn ($g) => $g->month,
                $report->generated,
            ));
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    #[Test]
    public function ne_regenere_pas_les_annexes_deja_emises(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::create(2025, 6, 1));
        try {
            [$user, $company, $vehicle] = $this->seedCompanyVehiclePricing(2024);

            Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
                'start_date' => '2024-02-01', 'end_date' => '2024-02-05',
            ]);
            Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
                'start_date' => '2024-08-10', 'end_date' => '2024-08-15',
            ]);

            $first = $this->action->execute(
                companyId: $company->id,
                year: 2024,
                generatedByUserId: $user->id,
                issuer: self::ISSUER,
            );
            $this->assertCount(2, $first->generated);
            $this->assertDatabaseCount('invoices', 2);

            // BillingBreakdownService est singleton (memo intra-requête en prod).
            // Simulons une seconde requête HTTP en vidant le cache d'instance,
            // sinon le breakdown lit le cache stale.
            $this->app->forgetInstance(BillingBreakdownService::class);
            $secondAction = $this->app->make(BulkGenerateInvoicesAction::class);

            $second = $secondAction->execute(
                companyId: $company->id,
                year: 2024,
                generatedByUserId: $user->id,
                issuer: self::ISSUER,
            );
            $this->assertCount(0, $second->generated);
            $this->assertCount(0, $second->failed);
            $this->assertDatabaseCount('invoices', 2);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    #[Test]
    public function ne_traite_que_les_mois_ecoules_de_l_annee_en_cours(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::create(2024, 5, 15));
        try {
            [$user, $company, $vehicle] = $this->seedCompanyVehiclePricing(2024);

            // Février (mois écoulé) garde, Mai (mois en cours) et
            // Juillet (mois futur) sont ignorés.
            Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
                'start_date' => '2024-02-05', 'end_date' => '2024-02-10',
            ]);
            Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
                'start_date' => '2024-05-02', 'end_date' => '2024-05-08',
            ]);
            Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
                'start_date' => '2024-07-05', 'end_date' => '2024-07-10',
            ]);

            $report = $this->action->execute(
                companyId: $company->id,
                year: 2024,
                generatedByUserId: $user->id,
                issuer: self::ISSUER,
            );

            $this->assertCount(1, $report->generated);
            $this->assertSame(2, $report->generated[0]->month);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    #[Test]
    public function retourne_un_rapport_vide_sans_erreur_quand_rien_n_est_a_generer(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::create(2025, 6, 1));
        try {
            $user = User::factory()->create();
            $company = Company::factory()->create();

            $report = $this->action->execute(
                companyId: $company->id,
                year: 2024,
                generatedByUserId: $user->id,
                issuer: self::ISSUER,
            );

            $this->assertCount(0, $report->generated);
            $this->assertCount(0, $report->failed);
            $this->assertSame($company->id, $report->companyId);
            $this->assertSame(2024, $report->year);
            $this->assertDatabaseCount('invoices', 0);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    /**
     * @return array{0: User, 1: Company, 2: Vehicle}
     */
    private function seedCompanyVehiclePricing(int $year): array
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $vehicle = Vehicle::factory()->create();
        VehicleYearlyPricing::factory()
            ->for($vehicle)
            ->forYear($year)
            ->withRates(9_000, 50_000, 180_000)
            ->create();

        return [$user, $company, $vehicle];
    }
}
