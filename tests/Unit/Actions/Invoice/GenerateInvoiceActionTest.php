<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Invoice;

use App\Actions\Invoice\GenerateInvoiceAction;
use App\Exceptions\Billing\MissingPricingException;
use App\Exceptions\Invoice\InvoiceAlreadyExistsException;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleYearlyPricing;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests d'intégration de `GenerateInvoiceAction` (Phase 14.E V1.2).
 *
 * Le PDF est rendu via dompdf — coût ~200ms par test, on couvre les
 * cas critiques sans exhaustivité (les calculs financiers sont déjà
 * couverts par `BillingCalculatorTest`).
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

        // Persistance Invoice + InvoiceLine.
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'company_id' => $company->id,
            'year' => 2024,
            'month' => 3,
            'invoice_number' => '2024-03-0001',
            'total_ht_cents' => 77_000,
            'generated_by_user_id' => $user->id,
        ]);
        $this->assertCount(1, $invoice->lines);
        $this->assertSame(10, $invoice->lines[0]->days_used);
        $this->assertSame(77_000, $invoice->lines[0]->total_ht_cents);
        $this->assertSame('AA-001-AA Renault Megane', $invoice->lines[0]->vehicle_label_snapshot);

        // PDF persiste sous le chemin attendu et le hash colle au binaire.
        Storage::disk('local')->assertExists($invoice->pdf_path);
        $binary = Storage::disk('local')->get($invoice->pdf_path);
        $this->assertNotNull($binary);
        $this->assertSame(hash('sha256', $binary), $invoice->pdf_hash);

        // Snippet de signature PDF (entête `%PDF-`).
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
        // Pas de tarif.
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

        // Aucune facture créée + aucun fichier PDF persisté.
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

        // Deuxième facture pour le même mois mais entreprise différente.
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
        // Simulons une collision filesystem en pré-créant le fichier
        // attendu : `InvoicePdfStorage::store()` refuse l'écrasement et
        // remonte une RuntimeException → la transaction rollback les
        // INSERT déjà passés.
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

        // Pré-pose le fichier qui sera tenté.
        Storage::disk('local')->put(
            "invoices/2024/{$company->id}/2024-03-0001.pdf",
            'collision',
        );

        $this->expectException(\RuntimeException::class);

        try {
            $this->action->execute(
                companyId: $company->id,
                year: 2024,
                month: 3,
                generatedByUserId: $user->id,
                issuer: self::ISSUER,
            );
        } finally {
            // Quoi qu'il arrive, les tables Invoice / InvoiceLine doivent
            // rester vides (rollback transactionnel).
            $this->assertSame(0, Invoice::query()->count());
            $this->assertSame(0, InvoiceLine::query()->count());
        }
    }
}
