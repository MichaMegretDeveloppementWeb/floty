<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Invoice;

use App\Actions\Invoice\RegenerateInvoiceAction;
use App\Exceptions\Billing\MissingPricingException;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleYearlyPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La régénération soft-delete l'ancienne facture et la marque obsolète +
 * `superseded_by_id` pointant vers la nouvelle. PDF préservé pour audit.
 * Nouveau numéro séquentiel (art. 242 nonies A annexe II CGI).
 */
final class RegenerateInvoiceActionTest extends TestCase
{
    use RefreshDatabase;

    private const ISSUER = [
        'name' => 'Sogema Rent',
        'addressLine1' => 'Vessac',
        'addressLine2' => null,
        'postalCode' => '12720',
        'city' => 'Saint-André-de-Vézines',
        'siren' => null,
        'contactEmail' => null,
    ];

    private RegenerateInvoiceAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->action = $this->app->make(RegenerateInvoiceAction::class);
    }

    #[Test]
    public function softdelete_ancienne_facture_et_cree_nouvelle_chainee(): void
    {
        [$user, $invoice] = $this->seedRegeneratableInvoice();
        $oldId = $invoice->id;
        $oldPath = $invoice->pdf_path;

        $newInvoice = $this->action->execute(
            invoice: $invoice,
            generatedByUserId: $user->id,
            issuer: self::ISSUER,
        );

        $this->assertDatabaseHas('invoices', [
            'id' => $oldId,
            'superseded_by_id' => $newInvoice->id,
        ]);
        $oldRow = Invoice::query()->withTrashed()->find($oldId);
        self::assertNotNull($oldRow);
        self::assertNotNull($oldRow->deleted_at);
        self::assertNotNull($oldRow->obsolete_at);

        self::assertNotSame($oldId, $newInvoice->id);
        self::assertNull($newInvoice->deleted_at);

        Storage::disk('local')->assertExists($oldPath);

        Storage::disk('local')->assertExists($newInvoice->pdf_path);
        self::assertNotSame($oldPath, $newInvoice->pdf_path);
    }

    #[Test]
    public function attribue_un_nouveau_numero_sequentiel_distinct(): void
    {
        [$user, $invoice] = $this->seedRegeneratableInvoice(invoiceNumber: '2024-03-0001');

        $newInvoice = $this->action->execute(
            invoice: $invoice,
            generatedByUserId: $user->id,
            issuer: self::ISSUER,
        );

        // Numérotation séquentielle continue (art. 242 nonies A).
        self::assertSame('2024-03-0002', $newInvoice->invoice_number);
    }

    #[Test]
    public function regeneration_remet_le_flag_is_divergent_a_false(): void
    {
        // La regénération doit produire `is_divergent = false` même
        // si l'ancienne était divergente.
        [$user, $invoice] = $this->seedRegeneratableInvoice();
        $invoice->update(['is_divergent' => true]);

        $newInvoice = $this->action->execute(
            invoice: $invoice,
            generatedByUserId: $user->id,
            issuer: self::ISSUER,
        );

        self::assertFalse($newInvoice->is_divergent);
    }

    #[Test]
    public function rollback_complet_si_missing_pricing_garde_ancienne_facture_et_pdf(): void
    {
        [$user, $invoice, $company, $vehicle] = $this->seedRegeneratableInvoice();
        $oldPath = $invoice->pdf_path;
        $oldId = $invoice->id;
        $oldNumber = $invoice->invoice_number;

        // Pricing supprimé entre création et regen : `Generate` lèvera
        // MissingPricingException. La transaction doit rollback la
        // suppression DB de l'ancienne facture.
        VehicleYearlyPricing::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('year', 2024)
            ->delete();

        try {
            $this->action->execute(
                invoice: $invoice,
                generatedByUserId: $user->id,
                issuer: self::ISSUER,
            );
            self::fail('Expected MissingPricingException');
        } catch (MissingPricingException) {
        }

        $oldRow = Invoice::query()->withTrashed()->find($oldId);
        self::assertNotNull($oldRow);
        self::assertNull($oldRow->deleted_at);
        self::assertNull($oldRow->obsolete_at);
        self::assertNull($oldRow->superseded_by_id);
        self::assertSame($oldNumber, $oldRow->invoice_number);

        Storage::disk('local')->assertExists($oldPath);

        self::assertSame(1, Invoice::query()
            ->withTrashed()
            ->where('company_id', $company->id)
            ->where('year', 2024)
            ->where('month', 3)
            ->count());
    }

    /**
     * @return array{0: User, 1: Invoice, 2: Company, 3: Vehicle}
     */
    private function seedRegeneratableInvoice(string $invoiceNumber = '2024-03-0001'): array
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
            'start_date' => '2024-03-01',
            'end_date' => '2024-03-10',
        ]);

        $pdfPath = sprintf('invoices/2024/%d/%s.pdf', $company->id, $invoiceNumber);

        $invoice = Invoice::factory()
            ->for($company)
            ->for($user, 'generatedBy')
            ->create([
                'year' => 2024,
                'month' => 3,
                'invoice_number' => $invoiceNumber,
                'pdf_path' => $pdfPath,
            ]);

        Storage::disk('local')->put($pdfPath, '%PDF-stub-old-content');

        return [$user, $invoice, $company, $vehicle];
    }
}
