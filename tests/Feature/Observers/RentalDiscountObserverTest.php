<?php

declare(strict_types=1);

namespace Tests\Feature\Observers;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\RentalDiscount;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests d'intégration du `RentalDiscountObserver` (Lot 3 chantier
 * RentalDiscount). Vérifie que toute mutation de réduction commerciale
 * marque `invoices.is_divergent = true` sur les factures déjà émises
 * dont la période (year × month) chevauche la réduction.
 *
 * Symétrique de {@see InvoiceDivergenceObserversTest} pour Contract /
 * VehicleYearlyPricing / Vehicle.
 */
final class RentalDiscountObserverTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function creating_a_discount_flips_invoices_within_period(): void
    {
        $company = Company::factory()->create();
        $invoiceMar = $this->seedCleanInvoice($company, 2024, 3);
        $invoiceJun = $this->seedCleanInvoice($company, 2024, 6);

        // Création couvre mars uniquement.
        RentalDiscount::factory()
            ->forCompany($company)
            ->withPeriod(CarbonImmutable::create(2024, 3, 1), CarbonImmutable::create(2024, 3, 31))
            ->withDiscountPercent(10)
            ->create();

        self::assertTrue($invoiceMar->refresh()->is_divergent, 'mars chevauche la réduction · flag attendu');
        self::assertFalse($invoiceJun->refresh()->is_divergent, 'juin hors période · flag inchangé');
    }

    #[Test]
    public function updating_discount_dates_flips_old_and_new_ranges(): void
    {
        $company = Company::factory()->create();
        $invoiceMar = $this->seedCleanInvoice($company, 2024, 3);
        $invoiceJun = $this->seedCleanInvoice($company, 2024, 6);

        $discount = RentalDiscount::factory()
            ->forCompany($company)
            ->withPeriod(CarbonImmutable::create(2024, 3, 1), CarbonImmutable::create(2024, 3, 31))
            ->withDiscountPercent(10)
            ->create();

        // La création a déjà flippé mars · reset pour observer
        // uniquement l'effet de l'update.
        Invoice::query()->update(['is_divergent' => false]);

        $discount->update([
            'start_date' => '2024-06-01',
            'end_date' => '2024-06-30',
        ]);

        self::assertTrue($invoiceMar->refresh()->is_divergent, 'ancien range mars doit être flaggué');
        self::assertTrue($invoiceJun->refresh()->is_divergent, 'nouveau range juin doit être flaggué');
    }

    #[Test]
    public function updating_only_label_does_not_flip_anything(): void
    {
        // Garde-fou · un changement purement annotatif (label / notes)
        // ne doit pas déclencher de flag. La sémantique commerciale n'a
        // pas changé.
        $company = Company::factory()->create();
        $discount = RentalDiscount::factory()
            ->forCompany($company)
            ->withPeriod(CarbonImmutable::create(2024, 3, 1), CarbonImmutable::create(2024, 3, 31))
            ->withDiscountPercent(10)
            ->create();

        Invoice::query()->update(['is_divergent' => false]);
        $invoice = $this->seedCleanInvoice($company, 2024, 3);

        $discount->update([
            'label' => 'Pack fidélité 2024 (renommage)',
            'notes' => 'Note ajoutée a posteriori',
        ]);

        self::assertFalse($invoice->refresh()->is_divergent);
    }

    #[Test]
    public function updating_basis_points_flips_invoices_within_period(): void
    {
        $company = Company::factory()->create();
        $discount = RentalDiscount::factory()
            ->forCompany($company)
            ->withPeriod(CarbonImmutable::create(2024, 3, 1), CarbonImmutable::create(2024, 3, 31))
            ->withDiscountPercent(10)
            ->create();

        Invoice::query()->update(['is_divergent' => false]);
        $invoice = $this->seedCleanInvoice($company, 2024, 3);

        // Changer le pourcentage = sémantique commerciale modifiée ·
        // les factures déjà émises ne reflètent plus le bon montant.
        $discount->update(['discount_basis_points' => 1500]);

        self::assertTrue($invoice->refresh()->is_divergent);
    }

    #[Test]
    public function soft_deleting_a_discount_flips_invoices_within_period(): void
    {
        $company = Company::factory()->create();
        $discount = RentalDiscount::factory()
            ->forCompany($company)
            ->withPeriod(CarbonImmutable::create(2024, 3, 1), CarbonImmutable::create(2024, 3, 31))
            ->withDiscountPercent(10)
            ->create();

        Invoice::query()->update(['is_divergent' => false]);
        $invoice = $this->seedCleanInvoice($company, 2024, 3);

        $discount->delete();

        self::assertTrue($invoice->refresh()->is_divergent);
    }

    #[Test]
    public function restoring_a_discount_flips_invoices_within_period(): void
    {
        $company = Company::factory()->create();
        $discount = RentalDiscount::factory()
            ->forCompany($company)
            ->withPeriod(CarbonImmutable::create(2024, 3, 1), CarbonImmutable::create(2024, 3, 31))
            ->withDiscountPercent(10)
            ->create();
        $discount->delete();

        Invoice::query()->update(['is_divergent' => false]);
        $invoice = $this->seedCleanInvoice($company, 2024, 3);

        $discount->restore();

        self::assertTrue($invoice->refresh()->is_divergent);
    }

    #[Test]
    public function discount_for_company_a_does_not_flip_invoices_of_company_b(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $invoiceA = $this->seedCleanInvoice($companyA, 2024, 3);
        $invoiceB = $this->seedCleanInvoice($companyB, 2024, 3);

        RentalDiscount::factory()
            ->forCompany($companyA)
            ->withPeriod(CarbonImmutable::create(2024, 3, 1), CarbonImmutable::create(2024, 3, 31))
            ->withDiscountPercent(10)
            ->create();

        self::assertTrue($invoiceA->refresh()->is_divergent);
        self::assertFalse($invoiceB->refresh()->is_divergent, 'la réduction est portée par companyA · companyB intacte');
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
