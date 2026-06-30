<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Invoice;

use App\Actions\Invoice\GenerateInvoiceAction;
use App\Contracts\Repositories\User\Invoice\InvoiceWriteRepositoryInterface;
use App\Exceptions\Billing\MissingPricingException;
use App\Exceptions\Invoice\InvoiceAlreadyExistsException;
use App\Exceptions\Invoice\InvoicePdfAlreadyExistsException;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleYearlyPricing;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * PDF rendu via dompdf (~200ms/test) : couvre les cas critiques.
 * Les calculs financiers sont couverts par BillingCalculatorTest.
 */
final class GenerateInvoiceActionTest extends TestCase
{
    use RefreshDatabase;

    private GenerateInvoiceAction $action;

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
        $this->action = $this->app->make(GenerateInvoiceAction::class);
    }

    #[Test]
    public function genere_une_facture_avec_lignes_pdf_persiste_et_hash(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['legal_name' => 'Cliente SA']);
        $vehicle = Vehicle::factory()->create([
            'license_plate' => 'AA-001-AA',
            'brand' => 'Renault',
            'model' => 'Megane',
        ]);
        VehicleYearlyPricing::factory()
            ->for($vehicle)
            ->forYear(2024)
            ->withRates(9_000, 50_000, 180_000)
            ->create();

        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => '2024-03-01',
            'end_date' => '2024-03-10',
        ]);

        $invoice = $this->action->execute(
            companyId: $company->id,
            year: 2024,
            month: 3,
            generatedByUserId: $user->id,
            issuer: self::ISSUER,
        );

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'company_id' => $company->id,
            'year' => 2024,
            'month' => 3,
            'invoice_number' => '2024-03-0001',
            'total_ht_cents' => 77_000,
            'generated_by_user_id' => $user->id,
        ]);
        // Facture neuve = pas de divergence.
        $this->assertFalse($invoice->is_divergent);
        $this->assertCount(1, $invoice->lines);
        $this->assertSame(10, $invoice->lines[0]->days_used);
        $this->assertSame(77_000, $invoice->lines[0]->total_ht_cents);
        $this->assertSame('AA-001-AA Renault Megane', $invoice->lines[0]->vehicle_label_snapshot);

        Storage::disk('local')->assertExists($invoice->pdf_path);
        $binary = Storage::disk('local')->get($invoice->pdf_path);
        $this->assertNotNull($binary);
        $this->assertSame(hash('sha256', $binary), $invoice->pdf_hash);

        $this->assertStringStartsWith('%PDF-', $binary);
    }

    #[Test]
    public function refuse_la_regeneration_pour_un_couple_deja_facture(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $vehicle = Vehicle::factory()->create();
        VehicleYearlyPricing::factory()
            ->for($vehicle)
            ->forYear(2024)
            ->withRates(9_000, 50_000, 180_000)
            ->create();
        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => '2024-03-01', 'end_date' => '2024-03-05',
        ]);

        $this->action->execute(
            companyId: $company->id,
            year: 2024,
            month: 3,
            generatedByUserId: $user->id,
            issuer: self::ISSUER,
        );

        $this->expectException(InvoiceAlreadyExistsException::class);

        $this->action->execute(
            companyId: $company->id,
            year: 2024,
            month: 3,
            generatedByUserId: $user->id,
            issuer: self::ISSUER,
        );
    }

    #[Test]
    public function leve_missing_pricing_quand_un_vehicule_n_a_pas_de_tarif(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $vehicle = Vehicle::factory()->create();
        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => '2024-03-01', 'end_date' => '2024-03-05',
        ]);

        $this->expectException(MissingPricingException::class);

        $this->action->execute(
            companyId: $company->id,
            year: 2024,
            month: 3,
            generatedByUserId: $user->id,
            issuer: self::ISSUER,
        );

        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('invoice_lines', 0);
    }

    #[Test]
    public function leve_model_not_found_si_l_entreprise_n_existe_pas(): void
    {
        $user = User::factory()->create();

        $this->expectException(ModelNotFoundException::class);

        $this->action->execute(
            companyId: 99999,
            year: 2024,
            month: 3,
            generatedByUserId: $user->id,
            issuer: self::ISSUER,
        );
    }

    #[Test]
    public function genere_une_facture_provisoire_pour_le_mois_en_cours(): void
    {
        // L'annexe du mois en cours est générable (provisoire, régénérable).
        CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 5, 15));
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $vehicle = Vehicle::factory()->create();
        VehicleYearlyPricing::factory()
            ->for($vehicle)
            ->forYear(2026)
            ->withRates(9_000, 50_000, 180_000)
            ->create();
        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => '2026-05-01', 'end_date' => '2026-05-10',
        ]);

        try {
            $invoice = $this->action->execute(
                companyId: $company->id,
                year: 2026,
                month: 5,
                generatedByUserId: $user->id,
                issuer: self::ISSUER,
            );
        } finally {
            CarbonImmutable::setTestNow();
        }

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'company_id' => $company->id,
            'year' => 2026,
            'month' => 5,
            'invoice_number' => '2026-05-0001',
        ]);
        $this->assertCount(1, $invoice->lines);
        $this->assertSame(10, $invoice->lines[0]->days_used);
    }

    #[Test]
    public function refuse_la_generation_pour_un_mois_futur(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 5, 15));
        $user = User::factory()->create();
        $company = Company::factory()->create();

        try {
            $this->expectException(DomainException::class);
            $this->action->execute(
                companyId: $company->id,
                year: 2026,
                month: 12,
                generatedByUserId: $user->id,
                issuer: self::ISSUER,
            );
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    #[Test]
    public function refuse_la_generation_pour_une_annee_future(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 5, 15));
        $user = User::factory()->create();
        $company = Company::factory()->create();

        try {
            $this->expectException(DomainException::class);
            $this->action->execute(
                companyId: $company->id,
                year: 2027,
                month: 1,
                generatedByUserId: $user->id,
                issuer: self::ISSUER,
            );
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    #[Test]
    public function attribue_des_numeros_sequentiels_pour_le_meme_mois(): void
    {
        $user = User::factory()->create();
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $vehicle = Vehicle::factory()->create();
        VehicleYearlyPricing::factory()
            ->for($vehicle)
            ->forYear(2024)
            ->withRates(9_000, 50_000, 180_000)
            ->create();

        Contract::factory()->forVehicle($vehicle)->forCompany($companyA)->create([
            'start_date' => '2024-03-01', 'end_date' => '2024-03-05',
        ]);

        $invoiceA = $this->action->execute(
            companyId: $companyA->id,
            year: 2024,
            month: 3,
            generatedByUserId: $user->id,
            issuer: self::ISSUER,
        );

        Contract::factory()->forVehicle($vehicle)->forCompany($companyB)->create([
            'start_date' => '2024-03-15', 'end_date' => '2024-03-19',
        ]);

        $invoiceB = $this->action->execute(
            companyId: $companyB->id,
            year: 2024,
            month: 3,
            generatedByUserId: $user->id,
            issuer: self::ISSUER,
        );

        $this->assertSame('2024-03-0001', $invoiceA->invoice_number);
        $this->assertSame('2024-03-0002', $invoiceB->invoice_number);
    }

    #[Test]
    public function rollback_complete_si_le_pdf_storage_echoue(): void
    {
        // Collision filesystem : `InvoicePdfStorage::store()` refuse
        // l'écrasement → InvoicePdfAlreadyExistsException, et la
        // transaction rollback les INSERT déjà passés.
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $vehicle = Vehicle::factory()->create();
        VehicleYearlyPricing::factory()
            ->for($vehicle)
            ->forYear(2024)
            ->withRates(9_000, 50_000, 180_000)
            ->create();
        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => '2024-03-01', 'end_date' => '2024-03-05',
        ]);

        Storage::disk('local')->put(
            "invoices/2024/{$company->id}/2024-03-0001.pdf",
            'collision',
        );

        $this->expectException(InvoicePdfAlreadyExistsException::class);

        try {
            $this->action->execute(
                companyId: $company->id,
                year: 2024,
                month: 3,
                generatedByUserId: $user->id,
                issuer: self::ISSUER,
            );
        } finally {
            $this->assertSame(0, Invoice::query()->count());
            $this->assertSame(0, InvoiceLine::query()->count());
        }
    }

    #[Test]
    public function cleanup_pdf_orphan_si_insert_db_echoue_apres_storage_put(): void
    {
        // `Storage::put` réussit puis l'INSERT DB échoue : try/catch
        // supprime le PDF orphelin avant re-throw (pas d'orphan file).
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $vehicle = Vehicle::factory()->create();
        VehicleYearlyPricing::factory()
            ->for($vehicle)
            ->forYear(2024)
            ->withRates(9_000, 50_000, 180_000)
            ->create();
        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => '2024-03-01', 'end_date' => '2024-03-05',
        ]);

        $expectedPdfPath = "invoices/2024/{$company->id}/2024-03-0001.pdf";

        // `persist` throw → crash DB après `Storage::put` réussi.
        $writerMock = Mockery::mock(InvoiceWriteRepositoryInterface::class);
        // @phpstan-ignore-next-line Mockery PHPStan stub friction
        $writerMock->shouldReceive('persist')->once()
            ->andThrow(new \RuntimeException('Simulated DB INSERT failure'));
        $this->app->instance(InvoiceWriteRepositoryInterface::class, $writerMock);

        $action = $this->app->make(GenerateInvoiceAction::class);

        try {
            $action->execute(
                companyId: $company->id,
                year: 2024,
                month: 3,
                generatedByUserId: $user->id,
                issuer: self::ISSUER,
            );
            self::fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            self::assertSame('Simulated DB INSERT failure', $e->getMessage());
        }

        $this->assertSame(0, Invoice::query()->count());
        Storage::disk('local')->assertMissing($expectedPdfPath);
    }
}
