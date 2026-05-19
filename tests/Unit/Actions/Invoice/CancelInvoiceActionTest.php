<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Invoice;

use App\Actions\Invoice\CancelInvoiceAction;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Annulation = seule mutation autorisée par ADR-0008 (immuabilité).
 * Suppression DB + cascade lignes + suppression PDF, en transaction.
 */
final class CancelInvoiceActionTest extends TestCase
{
    use RefreshDatabase;

    private CancelInvoiceAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->action = $this->app->make(CancelInvoiceAction::class);
    }

    #[Test]
    public function supprime_la_facture_les_lignes_et_le_pdf(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $invoice = Invoice::factory()
            ->for($company)
            ->for($user, 'generatedBy')
            ->create([
                'pdf_path' => 'invoices/2024/1/2024-01-0001.pdf',
            ]);

        Storage::disk('local')->put($invoice->pdf_path, '%PDF-stub-content');

        InvoiceLine::factory()->count(2)->for($invoice)->for($vehicle)->create();

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);
        $this->assertSame(2, InvoiceLine::query()->where('invoice_id', $invoice->id)->count());
        Storage::disk('local')->assertExists($invoice->pdf_path);

        $this->action->execute($invoice);

        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
        $this->assertSame(0, InvoiceLine::query()->where('invoice_id', $invoice->id)->count());
        Storage::disk('local')->assertMissing($invoice->pdf_path);
    }

    #[Test]
    public function idempotence_filesystem_si_pdf_deja_absent(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $invoice = Invoice::factory()
            ->for($company)
            ->for($user, 'generatedBy')
            ->create([
                'pdf_path' => 'invoices/2024/1/disparu.pdf',
            ]);

        Storage::disk('local')->assertMissing($invoice->pdf_path);

        $this->action->execute($invoice);

        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
    }
}
