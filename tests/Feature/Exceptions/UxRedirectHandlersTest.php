<?php

declare(strict_types=1);

namespace Tests\Feature\Exceptions;

use App\Exceptions\UserFacingExceptionRenderer;
use App\Models\BillingSettings;
use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractDocument;
use App\Models\Driver;
use App\Models\Invoice;
use App\Models\Unavailability;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use App\Models\VehicleYearlyPricing;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * Vérifie le comportement des render handlers UX pour ModelNotFoundException,
 * AuthorizationException et NotFoundHttpException (mapping + wiring HTTP).
 */
final class UxRedirectHandlersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->get(
            '/_test/throw-authorization',
            static fn () => throw new AuthorizationException('Forbidden test'),
        );
    }

    #[Test]
    public function model_not_found_company_redirige_vers_index_companies(): void
    {
        $response = $this->renderModelNotFound(Company::class);

        $response->assertRedirect(route('user.companies.index'));
        $response->assertSessionHas('toast-error');
        self::assertStringContainsString(
            'entreprise',
            (string) session('toast-error'),
        );
    }

    #[Test]
    public function model_not_found_vehicle_redirige_vers_index_vehicles(): void
    {
        $response = $this->renderModelNotFound(Vehicle::class);

        $response->assertRedirect(route('user.vehicles.index'));
        self::assertStringContainsString('véhicule', (string) session('toast-error'));
    }

    #[Test]
    public function model_not_found_driver_redirige_vers_index_drivers(): void
    {
        $response = $this->renderModelNotFound(Driver::class);

        $response->assertRedirect(route('user.drivers.index'));
        self::assertStringContainsString('conducteur', (string) session('toast-error'));
    }

    #[Test]
    public function model_not_found_contract_redirige_vers_index_contracts(): void
    {
        $response = $this->renderModelNotFound(Contract::class);

        $response->assertRedirect(route('user.contracts.index'));
        self::assertStringContainsString('contrat', (string) session('toast-error'));
    }

    #[Test]
    public function model_not_found_invoice_redirige_vers_index_invoices(): void
    {
        $response = $this->renderModelNotFound(Invoice::class);

        $response->assertRedirect(route('user.invoices.index'));
        self::assertStringContainsString('facture', (string) session('toast-error'));
    }

    #[Test]
    public function model_not_found_unavailability_redirige_vers_vehicles(): void
    {
        $response = $this->renderModelNotFound(Unavailability::class);

        $response->assertRedirect(route('user.vehicles.index'));
        self::assertStringContainsString('indisponibilité', (string) session('toast-error'));
    }

    #[Test]
    public function model_not_found_vehicle_yearly_pricing_redirige_vers_vehicles(): void
    {
        $response = $this->renderModelNotFound(VehicleYearlyPricing::class);

        $response->assertRedirect(route('user.vehicles.index'));
        self::assertStringContainsString('tarif', (string) session('toast-error'));
    }

    #[Test]
    public function model_not_found_vehicle_fiscal_characteristics_redirige_vers_vehicles(): void
    {
        $response = $this->renderModelNotFound(VehicleFiscalCharacteristics::class);

        $response->assertRedirect(route('user.vehicles.index'));
        self::assertStringContainsString('caractéristique', (string) session('toast-error'));
    }

    #[Test]
    public function model_not_found_contract_document_redirige_vers_contracts(): void
    {
        $response = $this->renderModelNotFound(ContractDocument::class);

        $response->assertRedirect(route('user.contracts.index'));
        self::assertStringContainsString('document', (string) session('toast-error'));
    }

    #[Test]
    public function model_not_found_billing_settings_redirige_vers_dashboard(): void
    {
        $response = $this->renderModelNotFound(BillingSettings::class);

        $response->assertRedirect(route('user.dashboard'));
        self::assertStringContainsString('paramètres', (string) session('toast-error'));
    }

    #[Test]
    public function model_not_found_unknown_model_fallback_dashboard(): void
    {
        $response = $this->renderModelNotFound(UnmappedTestModel::class);

        $response->assertRedirect(route('user.dashboard'));
        self::assertStringContainsString('élément', (string) session('toast-error'));
    }

    #[Test]
    public function authorization_exception_redirige_vers_dashboard(): void
    {
        $response = UserFacingExceptionRenderer::renderAuthorization(
            new AuthorizationException('Forbidden'),
        );

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(route('user.dashboard'), $response->getTargetUrl());
        self::assertSame(
            "Vous n'avez pas accès à cet élément.",
            $response->getSession()?->get('toast-error'),
        );
    }

    #[Test]
    public function not_found_http_redirige_vers_dashboard(): void
    {
        $response = UserFacingExceptionRenderer::renderNotFoundHttp(
            new NotFoundHttpException('Not found'),
        );

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(route('user.dashboard'), $response->getTargetUrl());
        self::assertSame(
            "Cette page n'existe pas ou a été supprimée.",
            $response->getSession()?->get('toast-error'),
        );
    }

    #[Test]
    public function get_company_inexistante_redirige_vers_index_avec_toast(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/app/companies/99999')
            ->assertRedirect(route('user.companies.index'))
            ->assertSessionHas('toast-error');
    }

    #[Test]
    public function get_driver_inexistant_redirige_vers_index_avec_toast(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/app/drivers/99999')
            ->assertRedirect(route('user.drivers.index'))
            ->assertSessionHas('toast-error');
    }

    #[Test]
    public function get_url_inexistante_redirige_vers_dashboard_avec_toast(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/app/foo/bar/baz')
            ->assertRedirect(route('user.dashboard'))
            ->assertSessionHas('toast-error');
    }

    #[Test]
    public function get_url_inexistante_avec_accept_json_renvoie_404_standard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/app/foo/bar/baz')
            ->assertStatus(404);
    }

    #[Test]
    public function authorization_exception_via_route_test_redirige_vers_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/_test/throw-authorization')
            ->assertRedirect(route('user.dashboard'))
            ->assertSessionHas('toast-error');
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return TestResponse<RedirectResponse>
     */
    private function renderModelNotFound(string $modelClass): TestResponse
    {
        $exception = (new ModelNotFoundException)->setModel($modelClass);
        $response = UserFacingExceptionRenderer::renderModelNotFound($exception);
        $response->getSession()?->ageFlashData();

        return TestResponse::fromBaseResponse($response);
    }
}

final class UnmappedTestModel extends Model
{
    protected $table = '_test_unmapped';
}
