<?php

declare(strict_types=1);

namespace Tests\Feature\User\RentalDiscount;

use App\Models\Company;
use App\Models\RentalDiscount;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests pour le CRUD complet RentalDiscount (Lot 4 du chantier
 * RentalDiscount). Couvre les 8 endpoints du
 * {@see App\Http\Controllers\User\RentalDiscount\RentalDiscountController}.
 *
 * Garanties · pages Inertia correctement composées, payloads validés,
 * Actions appelées, redirections + toast OK, garde-fou chevauchement
 * actif sur store / update, endpoint AJAX checkConflicts.
 */
final class RentalDiscountControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    #[Test]
    public function index_renders_with_pagination_and_stats(): void
    {
        $company = Company::factory()->create();
        $today = CarbonImmutable::now()->startOfDay();

        // 1 active, 1 planned, 1 expired.
        RentalDiscount::factory()->forCompany($company)
            ->withPeriod($today->subDays(10), $today->addDays(20))
            ->withDiscountPercent(10)
            ->create();
        RentalDiscount::factory()->forCompany($company)
            ->withPeriod($today->addDays(30), $today->addDays(60))
            ->withDiscountPercent(5)
            ->create();
        RentalDiscount::factory()->forCompany($company)
            ->withPeriod($today->subDays(60), $today->subDays(30))
            ->withDiscountPercent(15)
            ->create();

        $response = $this->actingAs($this->user)->get('/app/rental-discounts');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('User/RentalDiscounts/Index/Index')
            ->where('hasAnyRentalDiscount', true)
            ->where('stats.active', 1)
            ->where('stats.planned', 1)
            ->where('stats.expired', 1)
            ->where('rentalDiscounts.meta.total', 3));
    }

    #[Test]
    public function index_exposes_has_any_false_when_no_discount(): void
    {
        $response = $this->actingAs($this->user)->get('/app/rental-discounts');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('hasAnyRentalDiscount', false)
            ->where('rentalDiscounts.meta.total', 0));
    }

    #[Test]
    public function index_filter_by_status_active_only_returns_active(): void
    {
        $company = Company::factory()->create();
        $today = CarbonImmutable::now()->startOfDay();

        RentalDiscount::factory()->forCompany($company)
            ->withPeriod($today->subDays(10), $today->addDays(20))
            ->withDiscountPercent(10)
            ->create();
        RentalDiscount::factory()->forCompany($company)
            ->withPeriod($today->addDays(30), $today->addDays(60))
            ->withDiscountPercent(5)
            ->create();

        $response = $this->actingAs($this->user)
            ->get('/app/rental-discounts?status=active');

        $response->assertInertia(fn ($page) => $page
            ->where('rentalDiscounts.meta.total', 1));
    }

    #[Test]
    public function show_renders_with_full_detail(): void
    {
        $company = Company::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $discount = RentalDiscount::factory()->forCompany($company)
            ->withPeriod(CarbonImmutable::create(2024, 1, 1), CarbonImmutable::create(2024, 6, 30))
            ->withDiscountPercent(10)
            ->appliesToVehicles([$vehicle])
            ->create(['label' => 'Pack test']);

        $response = $this->actingAs($this->user)
            ->get('/app/rental-discounts/'.$discount->id);

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('User/RentalDiscounts/Show/Index')
            ->where('rentalDiscount.id', $discount->id)
            ->where('rentalDiscount.label', 'Pack test')
            ->where('rentalDiscount.discountBasisPoints', 1000)
            ->where('rentalDiscount.isAllVehicles', false)
            ->where('rentalDiscount.vehicles.0.id', $vehicle->id));
    }

    #[Test]
    public function show_redirects_to_index_when_not_found(): void
    {
        // ModelNotFoundException · le handler global redirige vers
        // l'Index avec toast (cf. UserFacingExceptionRenderer).
        $this->actingAs($this->user)
            ->get('/app/rental-discounts/99999')
            ->assertRedirect('/app/rental-discounts');
    }

    #[Test]
    public function create_renders_with_companies_and_vehicles_options(): void
    {
        Company::factory()->count(2)->create();
        Vehicle::factory()->count(3)->create();

        $response = $this->actingAs($this->user)->get('/app/rental-discounts/create');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('User/RentalDiscounts/Create/Index')
            ->has('companies', 2)
            ->has('vehicles', 3));
    }

    #[Test]
    public function store_creates_a_discount(): void
    {
        $company = Company::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $response = $this->actingAs($this->user)->postJson('/app/rental-discounts', [
            'company_id' => $company->id,
            'start_date' => '2024-01-01',
            'end_date' => '2024-06-30',
            'discount_basis_points' => 1050,
            'label' => 'Test pack',
            'notes' => null,
            'vehicle_ids' => [$vehicle->id],
        ]);

        $created = RentalDiscount::query()->latest('id')->first();
        self::assertNotNull($created);
        $response->assertRedirect('/app/rental-discounts/'.$created->id);

        self::assertSame($company->id, $created->company_id);
        self::assertSame(1050, $created->discount_basis_points);
        self::assertSame('Test pack', $created->label);
        self::assertSame([$vehicle->id], $created->vehicles->pluck('id')->all());
    }

    #[Test]
    public function store_rejects_overlapping_period(): void
    {
        $company = Company::factory()->create();
        RentalDiscount::factory()->forCompany($company)
            ->withPeriod(CarbonImmutable::create(2024, 1, 1), CarbonImmutable::create(2024, 6, 30))
            ->withDiscountPercent(10)
            ->create();

        // Pas de pivot = applique à tous · forcément en conflit.
        $response = $this->actingAs($this->user)->postJson('/app/rental-discounts', [
            'company_id' => $company->id,
            'start_date' => '2024-05-01',
            'end_date' => '2024-08-31',
            'discount_basis_points' => 500,
            'vehicle_ids' => [],
        ]);

        // JSON · 422 avec erreur sur start_date (cf. controller catch +
        // ValidationException::withMessages sur le champ start_date).
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['start_date']);
        self::assertSame(1, RentalDiscount::query()->count());
    }

    #[Test]
    public function edit_renders_with_existing_payload(): void
    {
        $company = Company::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $discount = RentalDiscount::factory()->forCompany($company)
            ->withPeriod(CarbonImmutable::create(2024, 1, 1), CarbonImmutable::create(2024, 6, 30))
            ->withDiscountPercent(10)
            ->appliesToVehicles([$vehicle])
            ->create();

        $response = $this->actingAs($this->user)
            ->get('/app/rental-discounts/'.$discount->id.'/edit');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('User/RentalDiscounts/Edit/Index')
            ->where('rentalDiscount.id', $discount->id)
            ->has('vehicles'));
    }

    #[Test]
    public function update_changes_the_discount(): void
    {
        $company = Company::factory()->create();
        $discount = RentalDiscount::factory()->forCompany($company)
            ->withPeriod(CarbonImmutable::create(2024, 1, 1), CarbonImmutable::create(2024, 6, 30))
            ->withDiscountPercent(10)
            ->create(['label' => 'Old']);

        $response = $this->actingAs($this->user)
            ->patchJson('/app/rental-discounts/'.$discount->id, [
                'start_date' => '2024-02-01',
                'end_date' => '2024-07-31',
                'discount_basis_points' => 1500,
                'label' => 'New label',
                'notes' => 'Some notes',
                'vehicle_ids' => [],
            ]);

        $response->assertRedirect('/app/rental-discounts/'.$discount->id);
        $refreshed = $discount->refresh();
        self::assertSame(1500, $refreshed->discount_basis_points);
        self::assertSame('New label', $refreshed->label);
        self::assertSame('2024-02-01', $refreshed->start_date->toDateString());
    }

    #[Test]
    public function destroy_soft_deletes_the_discount(): void
    {
        $company = Company::factory()->create();
        $discount = RentalDiscount::factory()->forCompany($company)
            ->withPeriod(CarbonImmutable::create(2024, 1, 1), CarbonImmutable::create(2024, 6, 30))
            ->create();

        $response = $this->actingAs($this->user)
            ->delete('/app/rental-discounts/'.$discount->id);

        $response->assertRedirect('/app/rental-discounts');
        self::assertNotNull($discount->refresh()->deleted_at);
    }

    #[Test]
    public function check_conflicts_returns_overlapping_discounts(): void
    {
        $company = Company::factory()->create();
        RentalDiscount::factory()->forCompany($company)
            ->withPeriod(CarbonImmutable::create(2024, 1, 1), CarbonImmutable::create(2024, 6, 30))
            ->withDiscountPercent(10)
            ->create(['label' => 'Existing']);

        $response = $this->actingAs($this->user)
            ->postJson('/app/rental-discounts/check-conflicts', [
                'company_id' => $company->id,
                'start_date' => '2024-05-01',
                'end_date' => '2024-08-31',
                'vehicle_ids' => [],
            ]);

        $response->assertOk();
        $response->assertJsonPath('hasConflicts', true);
        $response->assertJsonCount(1, 'conflicts');
        $response->assertJsonPath('conflicts.0.label', 'Existing');
    }

    #[Test]
    public function check_conflicts_returns_empty_when_no_overlap(): void
    {
        $company = Company::factory()->create();
        RentalDiscount::factory()->forCompany($company)
            ->withPeriod(CarbonImmutable::create(2024, 1, 1), CarbonImmutable::create(2024, 6, 30))
            ->create();

        $response = $this->actingAs($this->user)
            ->postJson('/app/rental-discounts/check-conflicts', [
                'company_id' => $company->id,
                'start_date' => '2024-09-01',
                'end_date' => '2024-12-31',
                'vehicle_ids' => [],
            ]);

        $response->assertOk();
        $response->assertJsonPath('hasConflicts', false);
        $response->assertJsonCount(0, 'conflicts');
    }

    #[Test]
    public function check_conflicts_excludes_self_when_editing(): void
    {
        $company = Company::factory()->create();
        $self = RentalDiscount::factory()->forCompany($company)
            ->withPeriod(CarbonImmutable::create(2024, 1, 1), CarbonImmutable::create(2024, 6, 30))
            ->create();

        $response = $this->actingAs($this->user)
            ->postJson('/app/rental-discounts/check-conflicts', [
                'company_id' => $company->id,
                'start_date' => '2024-02-01',
                'end_date' => '2024-07-31',
                'vehicle_ids' => [],
                'exclude_id' => $self->id,
            ]);

        $response->assertOk();
        $response->assertJsonPath('hasConflicts', false);
    }
}
