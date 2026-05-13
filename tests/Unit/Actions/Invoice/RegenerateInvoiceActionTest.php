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
 * Tests de `RegenerateInvoiceAction` (refonte D5.10.P · option C historique).
 *
 * La régénération soft-delete l'ancienne facture et la marque obsolète +
 * `superseded_by_id` pointant vers la nouvelle. Le PDF de l'ancienne
 * reste sur disque pour l'audit trail. La nouvelle facture obtient un
 * nouveau numéro séquentiel (cf. art. 242 nonies A annexe II CGI).
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

        // L'ancienne row existe toujours en base (soft-delete) avec son
        // marqueur d'obsolescence et `superseded_by_id` posé.
        $this->assertDatabaseHas('invoices', [
            'id' => $oldId,
            'superseded_by_id' => $newInvoice->id,
        ]);
        $oldRow = Invoice::query()->withTrashed()->find($oldId);
        self::assertNotNull($oldRow);
        self::assertNotNull($oldRow->deleted_at);
        self::assertNotNull($oldRow->obsolete_at);

        // La nouvelle est un id distinct, non soft-deletée.
        self::assertNotSame($oldId, $newInvoice->id);
        self::assertNull($newInvoice->deleted_at);

        // Le PDF de l'ancienne est préservé sur disque (audit trail).
        Storage::disk('local')->assertExists($oldPath);

        // Le PDF de la nouvelle existe à un path distinct (nouveau numéro).
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

        // Numérotation séquentielle continue (jamais de réutilisation,
        // jamais de suffixe « bis ») · conforme à l'art. 242 nonies A.
        self::assertSame('2024-03-0002', $newInvoice->invoice_number);
    }

    #[Test]
    public function regeneration_remet_le_flag_is_divergent_a_false(): void
    {
        // T6 / Phase 14.R : la regénération doit produire une nouvelle
        // facture avec `is_divergent = false` même si l'ancienne était
        // flaggée à `true` (cas typique : l'utilisateur regénère
        // précisément parce qu'elle était divergente).
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

        // Suppression du pricing entre la création initiale et la regen :
        // le `Generate` lèvera `MissingPricingException`. La transaction
        // doit rollback la suppression DB de l'ancienne facture.
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
            // attendu
        }

        // L'ancienne facture est intégralement préservée et non
        // soft-deletée (transaction rollback).
        $oldRow = Invoice::query()->withTrashed()->find($oldId);
        self::assertNotNull($oldRow);
        self::assertNull($oldRow->deleted_at);
        self::assertNull($oldRow->obsolete_at);
        self::assertNull($oldRow->superseded_by_id);
        self::assertSame($oldNumber, $oldRow->invoice_number);

        Storage::disk('local')->assertExists($oldPath);

        // Aucune nouvelle facture n'a été créée.
        self::assertSame(1, Invoice::query()
            ->withTrashed()
            ->where('company_id', $company->id)
            ->where('year', 2024)
            ->where('month', 3)
            ->count());
    }

    /**
     * Crée une facture régénérable : Company, Vehicle, Pricing 2024 et un
     * Contract qui couvre mars 2024 (10 jours) + l'enregistrement Invoice
     * + un PDF stub sur le filesystem fake.
     *
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
