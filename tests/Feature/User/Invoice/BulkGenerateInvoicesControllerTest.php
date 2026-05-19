<?php

declare(strict_types=1);

namespace Tests\Feature\User\Invoice;

use App\Data\User\Invoice\BulkInvoiceGenerationReportData;
use App\Models\Company;
use App\Models\Contract;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleYearlyPricing;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests Feature de l'endpoint POST `/app/invoices/bulk-generate`
 * (génération en masse des annexes d'un couple entreprise × année).
 */
final class BulkGenerateInvoicesControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    #[Test]
    public function refuse_un_utilisateur_non_authentifie(): void
    {
        $this->post('/app/invoices/bulk-generate', [
            'company_id' => 1,
            'year' => 2024,
        ])->assertRedirect(); // Redirect vers login.
    }

    #[Test]
    public function rejette_un_payload_avec_company_inexistante(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/app/invoices/bulk-generate', [
                'company_id' => 999999,
                'year' => 2024,
            ])
            ->assertSessionHasErrors(['company_id' => 'Entreprise introuvable.']);
    }

    #[Test]
    public function rejette_un_payload_avec_year_hors_bornes(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $this->actingAs($user)
            ->post('/app/invoices/bulk-generate', [
                'company_id' => $company->id,
                'year' => 1900,
            ])
            ->assertSessionHasErrors('year');
    }

    #[Test]
    public function flash_un_toast_info_quand_rien_a_generer(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::create(2025, 6, 1));
        try {
            $user = User::factory()->create();
            $company = Company::factory()->create();

            $response = $this->actingAs($user)
                ->post('/app/invoices/bulk-generate', [
                    'company_id' => $company->id,
                    'year' => 2024,
                ]);

            $response
                ->assertRedirect()
                ->assertSessionHas('toast-info', fn (string $msg): bool => str_contains($msg, '2024'));

            $this->assertDatabaseCount('invoices', 0);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    #[Test]
    public function genere_toutes_les_annexes_et_flash_un_toast_success_avec_le_rapport(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::create(2025, 6, 1));
        try {
            $user = User::factory()->create();
            $company = Company::factory()->create();
            $vehicle = Vehicle::factory()->create();
            VehicleYearlyPricing::factory()
                ->for($vehicle)
                ->forYear(2024)
                ->withRates(9_000, 50_000, 180_000)
                ->create();

            Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
                'start_date' => '2024-02-10', 'end_date' => '2024-02-15',
            ]);
            Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
                'start_date' => '2024-05-02', 'end_date' => '2024-05-08',
            ]);

            $response = $this->actingAs($user)
                ->post('/app/invoices/bulk-generate', [
                    'company_id' => $company->id,
                    'year' => 2024,
                ]);

            $response
                ->assertRedirect()
                ->assertSessionHas('toast-success', fn (string $msg): bool => str_contains($msg, 'générées') && str_contains($msg, '2024'))
                ->assertSessionHas('bulkInvoiceReport');

            $this->assertDatabaseCount('invoices', 2);

            // Le rapport flash contient bien les 2 lignes generated.
            // Le flash bag Laravel applique `Arrayable::toArray()` au
            // stockage · on rehydrate via `from()` pour comparer
            // strictement la forme du DTO consommée côté Inertia.
            $report = BulkInvoiceGenerationReportData::from(
                session('bulkInvoiceReport'),
            );
            $this->assertCount(2, $report->generated);
            $this->assertCount(0, $report->failed);
            $this->assertSame($company->id, $report->companyId);
            $this->assertSame(2024, $report->year);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    #[Test]
    public function est_throttlee_a_deux_appels_par_minute(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        // 2 appels successifs · OK.
        $this->actingAs($user)
            ->post('/app/invoices/bulk-generate', [
                'company_id' => $company->id, 'year' => 2024,
            ])
            ->assertStatus(302);

        $this->actingAs($user)
            ->post('/app/invoices/bulk-generate', [
                'company_id' => $company->id, 'year' => 2024,
            ])
            ->assertStatus(302);

        // 3e appel · throttled (429).
        $this->actingAs($user)
            ->post('/app/invoices/bulk-generate', [
                'company_id' => $company->id, 'year' => 2024,
            ])
            ->assertStatus(429);
    }
}
