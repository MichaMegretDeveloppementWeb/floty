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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Vérifie le câblage du DTO `App\Data\User\Invoice\RegenerateInvoiceRequestData`
 * dans le controller : la cible de redirection est bien lue côté backend
 * et conduit à la bonne route après régénération réelle (pas de mock).
 *
 * Le setup pré-crée une facture + ses dépendances (Vehicle, Pricing,
 * Contract) pour que la régénération puisse réellement aboutir et
 * fournir l'`id` de la nouvelle facture nécessaire au redirect.
 */
final class InvoiceRegenerateRedirectTargetTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function regenerate_avec_target_show_redirige_vers_show_nouvelle_facture(): void
    {
        [$user, $invoice] = $this->setupRegeneratableInvoice();

        $response = $this->actingAs($user)
            ->post(
                route('user.invoices.regenerate', ['invoice' => $invoice->id]),
                ['redirect_target' => 'show'],
            )
            ->assertSessionHas('toast-success');

        // L'ID de la nouvelle facture diffère de l'ancien (annulée +
        // recréée). On vérifie juste que le redirect cible une URL Show.
        $response->assertRedirectToRoute('user.invoices.show', [
            'invoice' => Invoice::query()->latest('id')->firstOrFail()->id,
        ]);
    }

    #[Test]
    public function regenerate_avec_target_index_redirige_vers_index_factures(): void
    {
        [$user, $invoice] = $this->setupRegeneratableInvoice();

        $this->actingAs($user)
            ->post(
                route('user.invoices.regenerate', ['invoice' => $invoice->id]),
                ['redirect_target' => 'index'],
            )
            ->assertRedirect(route('user.invoices.index'))
            ->assertSessionHas('toast-success');
    }

    #[Test]
    public function regenerate_avec_target_company_tab_redirige_vers_companies_show_billing(): void
    {
        [$user, $invoice, $company] = $this->setupRegeneratableInvoice();

        $expectedUrl = route('user.companies.show', ['company' => $company->id]).'?tab=billing';

        $this->actingAs($user)
            ->post(
                route('user.invoices.regenerate', ['invoice' => $invoice->id]),
                ['redirect_target' => 'company-tab'],
            )
            ->assertRedirect($expectedUrl)
            ->assertSessionHas('toast-success');
    }

    #[Test]
    public function regenerate_sans_target_utilise_show_par_defaut(): void
    {
        [$user, $invoice] = $this->setupRegeneratableInvoice();

        $response = $this->actingAs($user)
            ->post(route('user.invoices.regenerate', ['invoice' => $invoice->id]))
            ->assertSessionHas('toast-success');

        $response->assertRedirectToRoute('user.invoices.show', [
            'invoice' => Invoice::query()->latest('id')->firstOrFail()->id,
        ]);
    }

    #[Test]
    public function regenerate_avec_target_invalide_renvoie_validation_error(): void
    {
        [$user, $invoice] = $this->setupRegeneratableInvoice();

        $this->actingAs($user)
            ->from(route('user.invoices.show', ['invoice' => $invoice->id]))
            ->post(
                route('user.invoices.regenerate', ['invoice' => $invoice->id]),
                ['redirect_target' => 'unknown-target'],
            )
            ->assertStatus(302)
            ->assertSessionHasErrors('redirect_target');
    }

    /**
     * Crée une facture régénérable : Company, Vehicle, Pricing 2024 et un
     * Contract qui couvre mars 2024 (10 jours), pour que le
     * BillingCalculator du `RegenerateInvoiceAction` puisse réellement
     * calculer une nouvelle facture.
     *
     * @return array{0: User, 1: Invoice, 2: Company}
     */
    private function setupRegeneratableInvoice(): array
    {
        Storage::fake('local');

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

        $invoice = Invoice::factory()
            ->forCompany($company)
            ->forYearMonth(2024, 3)
            ->create();

        InvoiceLine::factory()
            ->for($invoice)
            ->for($vehicle)
            ->create();

        return [$user, $invoice, $company];
    }
}
