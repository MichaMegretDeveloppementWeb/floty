<?php

declare(strict_types=1);

namespace Tests\Feature\User\Company;

use App\Models\Company;
use App\Models\Contract;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Guards the `companyYearPicker` payload feeding the "pick a company and
 * an exercise" shortcut modal on the invoices index, the declarations
 * index and the dashboard.
 *
 * Two properties matter:
 *   - it is `Inertia::optional()`, so no screen pays for it at mount
 *     (`InvoiceIndexQueryCountTest` forbids any `contracts` read there);
 *   - its content is identical whatever the screen it is served from.
 */
final class CompanyYearPickerPropTest extends TestCase
{
    use RefreshDatabase;

    private const NOW = '2026-06-15 10:00:00';

    /**
     * @return array<string, array{string, string}>
     */
    public static function screensProvider(): array
    {
        return [
            'annexes de facture' => ['/app/invoices', 'User/Invoices/Index/Index'],
            'declarations fiscales' => ['/app/declarations', 'User/Declarations/Index/Index'],
            'tableau de bord' => ['/app/dashboard', 'User/Dashboard/Index/Index'],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(self::NOW);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    #[Test]
    #[DataProvider('screensProvider')]
    public function la_prop_est_absente_du_rendu_initial(string $url, string $component): void
    {
        $user = User::factory()->create();
        Company::factory()->create();

        $this->actingAs($user)
            ->get($url)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component($component)
                ->missing('companyYearPicker'),
            );
    }

    #[Test]
    #[DataProvider('screensProvider')]
    public function la_prop_est_servie_sur_rechargement_partiel(string $url, string $component): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['legal_name' => 'Alpha Transports']);
        $this->contractStartingIn(2023, $company);

        $this->partialReload($user, $url, $component)
            ->assertOk()
            ->assertJsonCount(1, 'props.companyYearPicker.companies')
            ->assertJsonPath('props.companyYearPicker.companies.0.legalName', 'Alpha Transports')
            ->assertJsonPath('props.companyYearPicker.years', [2023, 2024, 2025, 2026])
            ->assertJsonPath('props.companyYearPicker.currentYear', 2026);
    }

    #[Test]
    public function les_entreprises_inactives_sont_exclues(): void
    {
        $user = User::factory()->create();
        Company::factory()->create(['legal_name' => 'Alpha Transports']);
        Company::factory()->inactive()->create(['legal_name' => 'Beta Logistique']);

        $this->partialReload($user, '/app/invoices', 'User/Invoices/Index/Index')
            ->assertOk()
            ->assertJsonCount(1, 'props.companyYearPicker.companies')
            ->assertJsonPath('props.companyYearPicker.companies.0.legalName', 'Alpha Transports');
    }

    #[Test]
    public function un_contrat_a_venir_n_etend_pas_les_annees_proposees(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $this->contractStartingIn(2025, $company);
        $this->contractStartingIn(2028, $company);

        // Les filtres d'index remontent jusqu'à 2028, le raccourci de
        // génération s'arrête à l'exercice en cours.
        $this->partialReload($user, '/app/invoices', 'User/Invoices/Index/Index')
            ->assertOk()
            ->assertJsonPath('props.companyYearPicker.years', [2025, 2026]);
    }

    #[Test]
    public function sans_aucun_contrat_seule_l_annee_courante_est_proposee(): void
    {
        $user = User::factory()->create();
        Company::factory()->create();

        $this->partialReload($user, '/app/invoices', 'User/Invoices/Index/Index')
            ->assertOk()
            ->assertJsonPath('props.companyYearPicker.years', [2026]);
    }

    private function contractStartingIn(int $year, Company $company): void
    {
        Contract::factory()
            ->forVehicle(Vehicle::factory()->create())
            ->forCompany($company)
            ->create([
                'start_date' => sprintf('%04d-03-01', $year),
                'end_date' => sprintf('%04d-03-31', $year),
            ]);
    }

    /**
     * Replays the real sequence: the screen is loaded, then opening the
     * modal fires `router.reload({ only: ['companyYearPicker'] })`. The
     * first visit is also what resolves the asset version the partial
     * request has to echo back.
     *
     * @return TestResponse<Response>
     */
    private function partialReload(User $user, string $url, string $component): TestResponse
    {
        $this->actingAs($user)->get($url)->assertOk();

        return $this->actingAs($user)->get($url, [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) Inertia::getVersion(),
            'X-Inertia-Partial-Component' => $component,
            'X-Inertia-Partial-Data' => 'companyYearPicker',
        ]);
    }
}
