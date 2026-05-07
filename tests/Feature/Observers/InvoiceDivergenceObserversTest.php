<?php

declare(strict_types=1);

namespace Tests\Feature\Observers;

use App\Enums\Vehicle\VehicleExitReason;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Vehicle;
use App\Models\VehicleYearlyPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests d'intégration des 3 observers (Contract / VehicleYearlyPricing /
 * Vehicle) qui flippent `invoices.is_divergent` à `true` (T6 / Phase 14.R).
 *
 * Le test crée une facture clean, applique une mutation déclenchante,
 * puis vérifie que le flag est passé à `true` sans avoir à appeler
 * explicitement `InvoiceDivergenceFlagger`.
 */
final class InvoiceDivergenceObserversTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function creating_a_contract_flips_invoices_du_meme_couple(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->seedCleanInvoice($company, 2024, 3);

        // Création d'un contrat couvrant mars 2024 → ContractObserver
        // doit flipper l'invoice à true.
        Contract::factory()
            ->forVehicle(Vehicle::factory()->create())
            ->forCompany($company)
            ->create([
                'start_date' => '2024-03-10',
                'end_date' => '2024-03-20',
            ]);

        self::assertTrue($invoice->refresh()->is_divergent);
    }

    #[Test]
    public function updating_a_contract_dates_flips_old_and_new_ranges(): void
    {
        $company = Company::factory()->create();
        $invoiceMar = $this->seedCleanInvoice($company, 2024, 3);
        $invoiceJun = $this->seedCleanInvoice($company, 2024, 6);

        $contract = Contract::factory()
            ->forVehicle(Vehicle::factory()->create())
            ->forCompany($company)
            ->create([
                'start_date' => '2024-03-10',
                'end_date' => '2024-03-20',
            ]);

        // Création a déjà flaggué mars. Reset pour tester l'update.
        Invoice::query()->update(['is_divergent' => false]);
        self::assertFalse($invoiceMar->refresh()->is_divergent);
        self::assertFalse($invoiceJun->refresh()->is_divergent);

        // Update : déplace le contrat de mars à juin → flip mars (old)
        // ET juin (new).
        $contract->update([
            'start_date' => '2024-06-05',
            'end_date' => '2024-06-15',
        ]);

        self::assertTrue($invoiceMar->refresh()->is_divergent);
        self::assertTrue($invoiceJun->refresh()->is_divergent);
    }

    #[Test]
    public function updating_a_contract_avec_seul_changement_de_notes_ne_flip_rien(): void
    {
        // Garde-fou : un changement purement annotatif (notes,
        // contract_reference) ne doit pas faire bouger les flags.
        $company = Company::factory()->create();
        $contract = Contract::factory()
            ->forVehicle(Vehicle::factory()->create())
            ->forCompany($company)
            ->create([
                'start_date' => '2024-03-10',
                'end_date' => '2024-03-20',
            ]);

        // Reset flag posé à la création.
        Invoice::query()->update(['is_divergent' => false]);
        $invoice = $this->seedCleanInvoice($company, 2024, 3);

        $contract->update(['notes' => 'note ajoutée a posteriori']);

        self::assertFalse($invoice->refresh()->is_divergent);
    }

    #[Test]
    public function creating_a_pricing_flips_invoices_du_year_des_companies_concernees(): void
    {
        $vehicle = Vehicle::factory()->create();
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        Contract::factory()->forVehicle($vehicle)->forCompany($companyA)->create([
            'start_date' => '2024-03-01', 'end_date' => '2024-03-15',
        ]);

        $invoiceA = $this->seedCleanInvoice($companyA, 2024, 3);
        $invoiceB = $this->seedCleanInvoice($companyB, 2024, 3); // pas de contrat sur véhicule

        VehicleYearlyPricing::factory()
            ->for($vehicle)
            ->forYear(2024)
            ->withRates(2_000, 10_000, 35_000)
            ->create();

        self::assertTrue($invoiceA->refresh()->is_divergent);
        self::assertFalse($invoiceB->refresh()->is_divergent);
    }

    #[Test]
    public function updating_vehicle_exit_date_flips_les_invoices_du_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create(['exit_date' => null]);
        $other = Vehicle::factory()->create();
        $company = Company::factory()->create();

        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => '2024-03-01', 'end_date' => '2024-03-15',
        ]);
        Contract::factory()->forVehicle($other)->forCompany($company)->create([
            'start_date' => '2024-04-01', 'end_date' => '2024-04-15',
        ]);

        // Reset les flags posés par les observers Contract.
        Invoice::query()->update(['is_divergent' => false]);

        $invoiceMatch = $this->seedCleanInvoice($company, 2024, 3);
        $invoiceOther = $this->seedCleanInvoice($company, 2024, 4);

        $vehicle->update([
            'exit_date' => '2024-03-10',
            'exit_reason' => VehicleExitReason::Sold,
        ]);

        self::assertTrue($invoiceMatch->refresh()->is_divergent);
        self::assertFalse($invoiceOther->refresh()->is_divergent);
    }

    #[Test]
    public function updating_vehicle_sans_changer_exit_date_ne_flip_rien(): void
    {
        // Garde-fou : un update Vehicle qui n'affecte pas exit_date
        // (e.g. mileage, notes, color) ne doit pas flagger.
        $vehicle = Vehicle::factory()->create();
        $company = Company::factory()->create();

        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => '2024-03-01', 'end_date' => '2024-03-15',
        ]);
        Invoice::query()->update(['is_divergent' => false]);

        $invoice = $this->seedCleanInvoice($company, 2024, 3);

        $vehicle->update(['mileage_current' => 99_999]);

        self::assertFalse($invoice->refresh()->is_divergent);
    }

    private function seedCleanInvoice(Company $company, int $year, int $month): Invoice
    {
        return Invoice::factory()
            ->for($company)
            ->create([
                'year' => $year,
                'month' => $month,
                'invoice_number' => sprintf('%d-%02d-%04d', $year, $month, random_int(1, 9999)),
                'is_divergent' => false,
            ]);
    }
}
