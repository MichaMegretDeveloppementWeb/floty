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
 * Tests de `RegenerateInvoiceAction` (chantier T4 / Phase 14.P).
 *
 * Vérifie l'orchestration `Cancel → Generate` :
 * - Happy path : ancienne facture remplacée par une nouvelle, ancien PDF
 *   supprimé du disque, nouveau numéro séquentiel attribué.
 * - Rollback : si `Generate` échoue (ex. `MissingPricingException`),
 *   l'ancienne facture est intégralement préservée (row DB + PDF disque),
 *   l'utilisateur peut réessayer après correction du périmètre.
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
    public function happy_path_supprime_ancienne_facture_creee_nouvelle(): void
    {
        [$user, $invoice] = $this->seedRegeneratableInvoice();
        $oldId = $invoice->id;
        $oldPdfBinary = Storage::disk('local')->get($invoice->pdf_path);

        $newInvoice = $this->action->execute(
            invoice: $invoice,
            generatedByUserId: $user->id,
            issuer: self::ISSUER,
        );

        // L'ancienne row n'existe plus, la nouvelle est différente.
        $this->assertDatabaseMissing('invoices', ['id' => $oldId]);
        self::assertNotSame($oldId, $newInvoice->id);

        // Une seule facture pour le couple (company × year × month).
        self::assertSame(1, Invoice::query()
            ->where('company_id', $newInvoice->company_id)
            ->where('year', $newInvoice->year)
            ->where('month', $newInvoice->month)
            ->count());

        // Le nouveau PDF existe (au même path si la séquence repart à 0001
        // ou à un path différent si la séquence avait déjà augmenté).
        Storage::disk('local')->assertExists($newInvoice->pdf_path);

        // Quand le path est identique (cas usuel : seule facture du mois),
        // le contenu binaire doit avoir été remplacé (nouveau hash).
        if ($newInvoice->pdf_path === $invoice->pdf_path) {
            $newPdfBinary = Storage::disk('local')->get($newInvoice->pdf_path);
            self::assertNotSame($oldPdfBinary, $newPdfBinary);
        }
    }

    #[Test]
    public function attribue_un_nouveau_numero_sequentiel(): void
    {
        [$user, $invoice] = $this->seedRegeneratableInvoice(invoiceNumber: '2024-03-0001');

        $newInvoice = $this->action->execute(
            invoice: $invoice,
            generatedByUserId: $user->id,
            issuer: self::ISSUER,
        );

        // L'ancienne séquence 0001 ayant été supprimée, le nouveau numéro
        // recalculé `MAX(seq) + 1` repart à 0001 (cas où la regen est la
        // 1ère facture du mois après suppression de l'ancienne).
        // Note : dans la vraie vie, si d'autres factures du même mois
        // (autres entreprises) existent, le numéro continuera à incrémenter.
        self::assertMatchesRegularExpression('/^2024-03-\d{4}$/', $newInvoice->invoice_number);
        self::assertNotSame('2024-03-0001-old', $newInvoice->invoice_number);
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

        // L'ancienne facture est intégralement préservée (DB + filesystem).
        $this->assertDatabaseHas('invoices', [
            'id' => $oldId,
            'invoice_number' => $oldNumber,
            'company_id' => $company->id,
        ]);
        Storage::disk('local')->assertExists($oldPath);

        // Aucune nouvelle facture n'a été créée.
        self::assertSame(1, Invoice::query()
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
