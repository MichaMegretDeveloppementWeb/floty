<?php

declare(strict_types=1);

namespace Tests\Feature\User\Invoice;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests Feature des pages Invoices Index + Show (Phase 14.F V1.2).
 */
final class InvoiceControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function index_expose_has_any_invoice_pour_decider_du_placeholder(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/app/invoices')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/Invoices/Index/Index')
                ->where('hasAnyInvoice', false));

        Invoice::factory()->create();

        $this->actingAs($user)
            ->get('/app/invoices')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('hasAnyInvoice', true));
    }

    #[Test]
    public function index_paginate_avec_filtres_company_year_month(): void
    {
        $user = User::factory()->create();
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        Invoice::factory()->forCompany($companyA)->forYearMonth(2024, 3)->create();
        Invoice::factory()->forCompany($companyA)->forYearMonth(2024, 4)->create();
        Invoice::factory()->forCompany($companyB)->forYearMonth(2024, 3)->create();

        // Filtre companyId=A : 2 résultats.
        $this->actingAs($user)
            ->get("/app/invoices?companyId={$companyA->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('invoices.data', 2));

        // Filtre year=2024&month=3 : 2 résultats (A et B en mars).
        $this->actingAs($user)
            ->get('/app/invoices?year=2024&month=3')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('invoices.data', 2));

        // Filtre combiné : 1 résultat (A en mars uniquement).
        $this->actingAs($user)
            ->get("/app/invoices?companyId={$companyA->id}&year=2024&month=3")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('invoices.data', 1));
    }

    #[Test]
    public function show_expose_les_lignes_et_metadonnees(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create([
            'short_code' => 'ACME',
            'legal_name' => 'ACME SAS',
        ]);
        $vehicle = Vehicle::factory()->create();

        $invoice = Invoice::factory()
            ->forCompany($company)
            ->forYearMonth(2024, 3)
            ->state(['generated_by_user_id' => $user->id])
            ->create();

        InvoiceLine::factory()
            ->for($invoice)
            ->for($vehicle)
            ->create();

        $this->actingAs($user)
            ->get("/app/invoices/{$invoice->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('User/Invoices/Show/Index')
                ->has('invoice', fn (AssertableInertia $i) => $i
                    ->where('id', $invoice->id)
                    ->where('companyShortCode', 'ACME')
                    ->where('companyLegalName', 'ACME SAS')
                    ->where('year', 2024)
                    ->where('month', 3)
                    ->has('lines', 1)
                    ->etc()));
    }

    #[Test]
    public function show_renvoie_404_si_facture_inexistante(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/app/invoices/99999')
            ->assertNotFound();
    }

    #[Test]
    public function download_renvoie_le_pdf_avec_le_bon_filename(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $invoice = Invoice::factory()->create([
            'invoice_number' => '2024-03-0042',
            'pdf_path' => 'invoices/2024/test/2024-03-0042.pdf',
        ]);

        Storage::disk('local')->put($invoice->pdf_path, '%PDF-1.4 fake content');

        $response = $this->actingAs($user)
            ->get("/app/invoices/{$invoice->id}/download")
            ->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString(
            'attachment; filename="2024-03-0042.pdf"',
            $response->headers->get('Content-Disposition'),
        );
    }

    #[Test]
    public function download_renvoie_404_si_pdf_disparu_du_filesystem(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $invoice = Invoice::factory()->create([
            'pdf_path' => 'invoices/2024/test/disparu.pdf',
        ]);

        // PDF NON poseé sur le filesystem fake.

        $this->actingAs($user)
            ->get("/app/invoices/{$invoice->id}/download")
            ->assertNotFound();
    }

    #[Test]
    public function destroy_supprime_la_facture_son_pdf_et_redirige_avec_toast(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $invoice = Invoice::factory()
            ->for($user, 'generatedBy')
            ->create([
                'pdf_path' => 'invoices/2024/1/2024-01-0001.pdf',
            ]);
        InvoiceLine::factory()->count(2)->for($invoice)->create();
        Storage::disk('local')->put($invoice->pdf_path, '%PDF-stub');

        $this->actingAs($user)
            ->delete("/app/invoices/{$invoice->id}")
            ->assertRedirect()
            ->assertSessionHas('toast-success');

        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
        $this->assertSame(0, InvoiceLine::query()->where('invoice_id', $invoice->id)->count());
        Storage::disk('local')->assertMissing($invoice->pdf_path);
    }
}
