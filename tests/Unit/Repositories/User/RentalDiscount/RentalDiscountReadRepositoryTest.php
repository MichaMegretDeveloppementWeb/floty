<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories\User\RentalDiscount;

use App\Models\Company;
use App\Models\RentalDiscount;
use App\Models\Vehicle;
use App\Repositories\User\RentalDiscount\RentalDiscountReadRepository;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre les lookups du repo RentalDiscount, notamment ·
 *   - filtrage par année (`findActiveForCompanyYear`)
 *   - batch multi-entreprises (`findActiveForCompaniesYear`)
 *   - existsAny et soft-delete handling
 *   - findActiveListingVehicleOn (sémantique « explicitement listé »)
 */
final class RentalDiscountReadRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private RentalDiscountReadRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new RentalDiscountReadRepository;
    }

    #[Test]
    public function exists_any_renvoie_false_quand_aucune_reduction(): void
    {
        self::assertFalse($this->repo->existsAny());
    }

    #[Test]
    public function exists_any_renvoie_true_quand_au_moins_une_reduction(): void
    {
        RentalDiscount::factory()->forCompany(Company::factory()->create())->create();

        self::assertTrue($this->repo->existsAny());
    }

    #[Test]
    public function exists_any_renvoie_false_si_seules_soft_deletees(): void
    {
        $discount = RentalDiscount::factory()->forCompany(Company::factory()->create())->create();
        $discount->delete();

        self::assertFalse($this->repo->existsAny());
    }

    #[Test]
    public function find_by_id_eager_load_vehicles(): void
    {
        $company = Company::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $discount = RentalDiscount::factory()
            ->forCompany($company)
            ->appliesToVehicles([$vehicle])
            ->create();

        $found = $this->repo->findById($discount->id);

        self::assertNotNull($found);
        self::assertTrue($found->relationLoaded('vehicles'));
        self::assertCount(1, $found->vehicles);
        self::assertSame($vehicle->id, $found->vehicles->first()?->id);
    }

    #[Test]
    public function find_by_id_renvoie_null_si_soft_deletee(): void
    {
        $discount = RentalDiscount::factory()->forCompany(Company::factory()->create())->create();
        $discount->delete();

        self::assertNull($this->repo->findById($discount->id));
    }

    #[Test]
    public function find_by_id_with_trashed_retrouve_les_soft_deletees(): void
    {
        $discount = RentalDiscount::factory()->forCompany(Company::factory()->create())->create();
        $discount->delete();

        $found = $this->repo->findByIdWithTrashed($discount->id);

        self::assertNotNull($found);
        self::assertNotNull($found->deleted_at);
    }

    #[Test]
    public function find_active_for_company_year_couvre_les_reductions_chevauchant_lannee(): void
    {
        $company = Company::factory()->create();

        // Avant l'année → exclue.
        RentalDiscount::factory()
            ->forCompany($company)
            ->withPeriod(CarbonImmutable::create(2023, 1, 1), CarbonImmutable::create(2023, 12, 31))
            ->create();

        // Chevauche 2024 (commence en 2023, finit en 2024) → incluse.
        $crossYear = RentalDiscount::factory()
            ->forCompany($company)
            ->withPeriod(CarbonImmutable::create(2023, 11, 1), CarbonImmutable::create(2024, 2, 28))
            ->create();

        // Pleine année 2024 → incluse.
        $fullYear = RentalDiscount::factory()
            ->forCompany($company)
            ->withPeriod(CarbonImmutable::create(2024, 1, 1), CarbonImmutable::create(2024, 12, 31))
            ->create();

        // Après → exclue.
        RentalDiscount::factory()
            ->forCompany($company)
            ->withPeriod(CarbonImmutable::create(2025, 1, 1), CarbonImmutable::create(2025, 6, 30))
            ->create();

        $found = $this->repo->findActiveForCompanyYear($company->id, 2024);

        self::assertCount(2, $found);
        $ids = $found->pluck('id')->all();
        self::assertContains($crossYear->id, $ids);
        self::assertContains($fullYear->id, $ids);
    }

    #[Test]
    public function find_active_for_companies_year_batch_multi_cies(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $companyC = Company::factory()->create();

        $rdA = RentalDiscount::factory()
            ->forCompany($companyA)
            ->withPeriod(CarbonImmutable::create(2024, 1, 1), CarbonImmutable::create(2024, 6, 30))
            ->create();
        $rdB = RentalDiscount::factory()
            ->forCompany($companyB)
            ->withPeriod(CarbonImmutable::create(2024, 3, 1), CarbonImmutable::create(2024, 9, 30))
            ->create();
        // Company C : aucune réduction 2024.
        RentalDiscount::factory()
            ->forCompany($companyC)
            ->withPeriod(CarbonImmutable::create(2023, 1, 1), CarbonImmutable::create(2023, 12, 31))
            ->create();

        $found = $this->repo->findActiveForCompaniesYear(
            [$companyA->id, $companyB->id, $companyC->id],
            2024,
        );

        self::assertCount(2, $found);
        $ids = $found->pluck('id')->all();
        self::assertContains($rdA->id, $ids);
        self::assertContains($rdB->id, $ids);
    }

    #[Test]
    public function find_active_for_companies_year_renvoie_collection_vide_si_input_vide(): void
    {
        $found = $this->repo->findActiveForCompaniesYear([], 2024);

        self::assertCount(0, $found);
    }

    #[Test]
    public function find_active_listing_vehicle_on_ignore_pivot_vide_tous_vehicules(): void
    {
        $company = Company::factory()->create();
        $vehicle = Vehicle::factory()->create();

        // Réduction "tous véhicules" (pivot vide) active aujourd'hui.
        RentalDiscount::factory()
            ->forCompany($company)
            ->withPeriod(CarbonImmutable::create(2024, 1, 1), CarbonImmutable::create(2030, 12, 31))
            ->create();

        // Le véhicule n'est pas explicitement listé → ne bloque pas
        // sa suppression future (sémantique du check).
        $found = $this->repo->findActiveListingVehicleOn($vehicle->id, '2024-06-01');

        self::assertCount(0, $found);
    }

    #[Test]
    public function find_active_listing_vehicle_on_retourne_si_explicitement_liste(): void
    {
        $company = Company::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $otherVehicle = Vehicle::factory()->create();

        $discount = RentalDiscount::factory()
            ->forCompany($company)
            ->withPeriod(CarbonImmutable::create(2024, 1, 1), CarbonImmutable::create(2024, 12, 31))
            ->appliesToVehicles([$vehicle, $otherVehicle])
            ->create();

        $found = $this->repo->findActiveListingVehicleOn($vehicle->id, '2024-06-01');

        self::assertCount(1, $found);
        self::assertSame($discount->id, $found->first()?->id);
    }

    #[Test]
    public function find_active_listing_vehicle_on_filtre_par_date(): void
    {
        $company = Company::factory()->create();
        $vehicle = Vehicle::factory()->create();

        // Réduction passée terminée.
        RentalDiscount::factory()
            ->forCompany($company)
            ->withPeriod(CarbonImmutable::create(2023, 1, 1), CarbonImmutable::create(2023, 12, 31))
            ->appliesToVehicles([$vehicle])
            ->create();

        // Aujourd'hui (2024) : pas active.
        $found = $this->repo->findActiveListingVehicleOn($vehicle->id, '2024-06-01');

        self::assertCount(0, $found);
    }
}
