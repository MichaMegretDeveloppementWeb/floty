<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Invoice;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Vehicle;
use App\Services\Invoice\InvoiceDivergenceFlagger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests d'intégration `InvoiceDivergenceFlagger` (T6 / Phase 14.R).
 *
 * Vérifient que le flag `is_divergent` est correctement posé en bulk
 * UPDATE pour les 3 cas d'invalidation (contrat, pricing year, exit_date)
 * sans appeler le BillingCalculator.
 */
final class InvoiceDivergenceFlaggerTest extends TestCase
{
    use RefreshDatabase;

    private InvoiceDivergenceFlagger $flagger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->flagger = $this->app->make(InvoiceDivergenceFlagger::class);
    }

    #[Test]
    public function flag_pour_contrat_simple_marque_les_invoices_du_range(): void
    {
        $company = Company::factory()->create();
        $invoiceFeb = $this->seedInvoice($company, 2024, 2);
        $invoiceMar = $this->seedInvoice($company, 2024, 3);
        $invoiceApr = $this->seedInvoice($company, 2024, 4);

        $count = $this->flagger->flagForContractRange(
            $company->id,
            '2024-02-15',
            '2024-03-20',
        );

        // Couverture : février et mars (avril hors range).
        self::assertSame(2, $count);
        self::assertTrue($invoiceFeb->refresh()->is_divergent);
        self::assertTrue($invoiceMar->refresh()->is_divergent);
        self::assertFalse($invoiceApr->refresh()->is_divergent);
    }

    #[Test]
    public function flag_pour_contrat_avec_changement_de_dates_marque_old_et_new(): void
    {
        $company = Company::factory()->create();
        $invoiceJan = $this->seedInvoice($company, 2024, 1);
        $invoiceFeb = $this->seedInvoice($company, 2024, 2);
        $invoiceJun = $this->seedInvoice($company, 2024, 6);
        $invoiceJul = $this->seedInvoice($company, 2024, 7);
        $invoiceMar = $this->seedInvoice($company, 2024, 3);

        // Ancien range : jan-fev, nouveau range : juin-juillet.
        // Mars n'est dans aucun des deux → reste false.
        $count = $this->flagger->flagForContractRange(
            $company->id,
            '2024-06-01',
            '2024-07-15',
            '2024-01-10',
            '2024-02-20',
        );

        self::assertSame(4, $count);
        self::assertTrue($invoiceJan->refresh()->is_divergent);
        self::assertTrue($invoiceFeb->refresh()->is_divergent);
        self::assertTrue($invoiceJun->refresh()->is_divergent);
        self::assertTrue($invoiceJul->refresh()->is_divergent);
        self::assertFalse($invoiceMar->refresh()->is_divergent);
    }

    #[Test]
    public function flag_pour_pricing_year_marque_uniquement_companies_concernees(): void
    {
        $vehicle = Vehicle::factory()->create();
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $companyC = Company::factory()->create();

        // CompanyA et CompanyB ont des contrats sur le véhicule en 2024.
        // CompanyC n'a pas de contrat → ne doit pas être flaggée.
        Contract::factory()->forVehicle($vehicle)->forCompany($companyA)->create([
            'start_date' => '2024-03-01',
            'end_date' => '2024-03-15',
        ]);
        Contract::factory()->forVehicle($vehicle)->forCompany($companyB)->create([
            'start_date' => '2024-06-01',
            'end_date' => '2024-06-30',
        ]);

        $invoiceA = $this->seedInvoice($companyA, 2024, 3);
        $invoiceB = $this->seedInvoice($companyB, 2024, 6);
        $invoiceC = $this->seedInvoice($companyC, 2024, 1); // pas de contrat
        $invoiceA2025 = $this->seedInvoice($companyA, 2025, 3); // autre année

        $count = $this->flagger->flagForVehiclePricingYear($vehicle->id, 2024);

        self::assertSame(2, $count);
        self::assertTrue($invoiceA->refresh()->is_divergent);
        self::assertTrue($invoiceB->refresh()->is_divergent);
        self::assertFalse($invoiceC->refresh()->is_divergent);
        self::assertFalse($invoiceA2025->refresh()->is_divergent);
    }

    #[Test]
    public function flag_pour_vehicle_marque_tous_les_couples_de_contrat(): void
    {
        $vehicle = Vehicle::factory()->create();
        $other = Vehicle::factory()->create();
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        Contract::factory()->forVehicle($vehicle)->forCompany($companyA)->create([
            'start_date' => '2024-03-01',
            'end_date' => '2024-03-15',
        ]);
        Contract::factory()->forVehicle($vehicle)->forCompany($companyB)->create([
            'start_date' => '2025-06-01',
            'end_date' => '2025-06-30',
        ]);
        // Contrat sur autre véhicule → ne doit pas flagger l'invoice associée.
        Contract::factory()->forVehicle($other)->forCompany($companyA)->create([
            'start_date' => '2024-04-01',
            'end_date' => '2024-04-30',
        ]);

        // Les observers Contract ont fired avant la création des invoices,
        // donc les invoices créées ci-après partent à false (default DB).
        $invoiceMatchA = $this->seedInvoice($companyA, 2024, 3);
        $invoiceMatchB = $this->seedInvoice($companyB, 2025, 6);
        $invoiceOther = $this->seedInvoice($companyA, 2024, 4); // autre véhicule

        $count = $this->flagger->flagForVehicle($vehicle->id);

        self::assertSame(2, $count);
        self::assertTrue($invoiceMatchA->refresh()->is_divergent);
        self::assertTrue($invoiceMatchB->refresh()->is_divergent);
        self::assertFalse($invoiceOther->refresh()->is_divergent);
    }

    #[Test]
    public function n_a_aucun_effet_si_aucune_invoice_match(): void
    {
        $company = Company::factory()->create();

        $count = $this->flagger->flagForContractRange(
            $company->id,
            '2024-02-15',
            '2024-03-20',
        );

        self::assertSame(0, $count);
    }

    #[Test]
    public function ne_re_flag_pas_une_invoice_deja_divergente(): void
    {
        // Idempotence : le filtre `is_divergent = false` dans le service
        // évite les UPDATE inutiles.
        $company = Company::factory()->create();
        $invoice = $this->seedInvoice($company, 2024, 3, isDivergent: true);

        $count = $this->flagger->flagForContractRange(
            $company->id,
            '2024-03-01',
            '2024-03-31',
        );

        // Aucune ligne flippée car déjà à true.
        self::assertSame(0, $count);
        self::assertTrue($invoice->refresh()->is_divergent);
    }

    private function seedInvoice(Company $company, int $year, int $month, bool $isDivergent = false): Invoice
    {
        return Invoice::factory()
            ->for($company)
            ->create([
                'year' => $year,
                'month' => $month,
                'invoice_number' => sprintf('%d-%02d-%04d', $year, $month, random_int(1, 9999)),
                'is_divergent' => $isDivergent,
            ]);
    }
}
