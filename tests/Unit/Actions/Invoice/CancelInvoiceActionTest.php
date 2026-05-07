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
 * Tests de `CancelInvoiceAction` (Phase 14.I V1.2).
 *
 * L'annulation est la seule mutation autorisée par la doctrine
 * immuabilité (ADR-0008). Vérifie : suppression DB de la facture +
 * cascade des lignes (FK ON DELETE CASCADE) + suppression du PDF du
 * disque, le tout dans une transaction.
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

        // Stub PDF on the fake disk.
        Storage::disk('local')->put($invoice->pdf_path, '%PDF-stub-content');

        // 2 lignes attachées (test cascade).
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

        // PDF jamais créé sur le disque (cas pathologique : suppression
        // manuelle hors-app). L'action doit fonctionner quand même.
        Storage::disk('local')->assertMissing($invoice->pdf_path);

        $this->action->execute($invoice);

        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
    }

    #[Test]
    public function execute_keeping_pdf_supprime_la_row_db_mais_garde_le_pdf_sur_disque(): void
    {
        // Variante orchestrée pour `RegenerateInvoiceAction` (chantier T4) :
        // la row + ses lignes sont supprimées dans la transaction, mais
        // le PDF reste sur disque. Le caller (Regenerate) s'occupe de la
        // suppression filesystem après son propre commit, ce qui garantit
        // que si `Generate` échoue, l'ancien PDF est encore présent.
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $invoice = Invoice::factory()
            ->for($company)
            ->for($user, 'generatedBy')
            ->create(['pdf_path' => 'invoices/2024/1/keep-me.pdf']);

        Storage::disk('local')->put($invoice->pdf_path, '%PDF-keep-me');
        InvoiceLine::factory()->count(2)->for($invoice)->for($vehicle)->create();

        $returnedPath = $this->action->executeKeepingPdf($invoice);

        // DB : row + lignes supprimées (cascade FK).
        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
        $this->assertSame(0, InvoiceLine::query()->where('invoice_id', $invoice->id)->count());

        // Filesystem : PDF toujours présent + path retourné au caller.
        Storage::disk('local')->assertExists('invoices/2024/1/keep-me.pdf');
        self::assertSame('invoices/2024/1/keep-me.pdf', $returnedPath);
    }
}
