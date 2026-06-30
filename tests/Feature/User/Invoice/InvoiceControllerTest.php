<?php

declare(strict_types=1);

namespace Tests\Feature\User\Invoice;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleYearlyPricing;
use Carbon\CarbonImmutable;
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
                    // P1.4 (audit perf 2026-05-16) · divergence servie
                    // en Inertia::defer, pas dans le DTO racine.
                    ->missing('divergence')
                    ->etc())
                ->missing('divergence'));
    }

    #[Test]
    public function show_redirige_avec_toast_si_facture_inexistante(): void
    {
        // Convention UX (chantier T2 / Phase 14.N) : un id invalide n'envoie
        // plus une page 404 mais redirige avec un toast-error explicite.
        // Depuis T7 (Phase 14.S), le controller utilise le route model
        // binding `Invoice $invoice` ; la `ModelNotFoundException` levée
        // par Laravel est mappée vers l'index Invoices par
        // `UserFacingExceptionRenderer::renderModelNotFound`.
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/app/invoices/99999')
            ->assertRedirect(route('user.invoices.index'))
            ->assertSessionHas('toast-error');
    }

    #[Test]
    public function generate_refuse_un_payload_avec_year_hors_plage(): void
    {
        // T7 (Phase 14.S) : `generate` valide via `GenerateInvoiceRequestData`
        // (Spatie Data DTO). Un payload avec `year` hors `[2020, 2099]`
        // doit être rejeté en 422 avec une erreur sur le champ.
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $this->actingAs($user)
            ->post('/app/invoices/generate', [
                'company_id' => $company->id,
                'year' => 2010,
                'month' => 3,
            ])
            ->assertSessionHasErrors('year');
    }

    #[Test]
    public function generate_refuse_un_payload_avec_company_inexistante(): void
    {
        // L'attribut `Exists('companies', 'id')` doit rejeter un id
        // inexistant et produire le message FR custom.
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/app/invoices/generate', [
                'company_id' => 999999,
                'year' => 2024,
                'month' => 3,
            ])
            ->assertSessionHasErrors(['company_id' => 'Entreprise introuvable.']);
    }

    #[Test]
    public function generate_reussit_pour_le_mois_en_cours(): void
    {
        // L'annexe du mois en cours est générable (provisoire). Le POST
        // aboutit à une facture et un toast-success.
        Storage::fake('local');
        CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 5, 17));

        try {
            $user = User::factory()->create();
            $company = Company::factory()->create();
            $vehicle = Vehicle::factory()->create();
            VehicleYearlyPricing::factory()
                ->for($vehicle)
                ->forYear(2026)
                ->withRates(9_000, 50_000, 180_000)
                ->create();
            Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
                'start_date' => '2026-05-01', 'end_date' => '2026-05-10',
            ]);

            $this->actingAs($user)
                ->post('/app/invoices/generate', [
                    'company_id' => $company->id,
                    'year' => 2026,
                    'month' => 5,
                ])
                ->assertRedirect()
                ->assertSessionHas('toast-success');

            $this->assertDatabaseHas('invoices', [
                'company_id' => $company->id,
                'year' => 2026,
                'month' => 5,
            ]);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    #[Test]
    public function generate_retourne_un_toast_pour_un_mois_futur_au_lieu_dun_500(): void
    {
        // Garde-fou défense en profondeur · le bouton « Générer » est
        // masqué côté UI pour les mois à venir, mais un POST forgé doit
        // aboutir à un toast-error propre plutôt qu'à un 500 brut
        // (incident prod 2026-05-17 · 500 sur un mois non générable).
        CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 5, 17));

        try {
            $user = User::factory()->create();
            $company = Company::factory()->create();

            $this->actingAs($user)
                ->post('/app/invoices/generate', [
                    'company_id' => $company->id,
                    'year' => 2026,
                    'month' => 7,
                ])
                ->assertRedirect()
                ->assertSessionHas('toast-error', fn (string $message): bool => str_contains($message, 'mois à venir'));
        } finally {
            CarbonImmutable::setTestNow();
        }
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
    public function download_redirige_vers_dashboard_si_pdf_disparu_du_filesystem(): void
    {
        // Le `abort(404)` du controller (PDF absent du disque) est
        // intercepté par le handler UX (T2) qui redirige vers le
        // dashboard avec un toast-error « page introuvable ».
        Storage::fake('local');

        $user = User::factory()->create();
        $invoice = Invoice::factory()->create([
            'pdf_path' => 'invoices/2024/test/disparu.pdf',
        ]);

        // PDF NON poseé sur le filesystem fake.

        $this->actingAs($user)
            ->get("/app/invoices/{$invoice->id}/download")
            ->assertRedirect(route('user.dashboard'))
            ->assertSessionHas('toast-error');
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
