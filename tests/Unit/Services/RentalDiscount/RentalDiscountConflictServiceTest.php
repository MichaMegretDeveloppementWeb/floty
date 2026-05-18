<?php

declare(strict_types=1);

namespace Tests\Unit\Services\RentalDiscount;

use App\Exceptions\RentalDiscount\RentalDiscountOverlapException;
use App\Models\Company;
use App\Models\RentalDiscount;
use App\Models\Vehicle;
use App\Repositories\User\RentalDiscount\RentalDiscountReadRepository;
use App\Services\RentalDiscount\RentalDiscountConflictService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre les 5 cas canoniques du non-chevauchement (cf. plan
 * `moonlit-hatching-mist.md` lot 1, section validation chevauchement).
 *
 * 1. périodes disjointes  → OK
 * 2. périodes chevauchantes, véhicules disjoints → OK
 * 3. périodes chevauchantes, intersection véhicules → CONFLIT
 * 4. existante "tous" (pivot vide) + candidate "subset" chevauchants → CONFLIT
 * 5. existante "tous" + candidate "tous" chevauchants → CONFLIT
 *
 * + edge cases sur les bornes inclusives (start = end voisin) et
 *   l'exclusion par id (mode édition).
 */
final class RentalDiscountConflictServiceTest extends TestCase
{
    use RefreshDatabase;

    private RentalDiscountConflictService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RentalDiscountConflictService(
            new RentalDiscountReadRepository,
        );
    }

    #[Test]
    public function periodes_disjointes_sans_conflit(): void
    {
        $company = Company::factory()->create();
        $vehicle = Vehicle::factory()->create();

        // Existante : T1 2024.
        RentalDiscount::factory()
            ->forCompany($company)
            ->withPeriod(CarbonImmutable::create(2024, 1, 1), CarbonImmutable::create(2024, 3, 31))
            ->appliesToVehicles([$vehicle])
            ->create();

        // Candidate : T2 2024 (disjoint).
        $conflicts = $this->service->findOverlapping(
            $company->id,
            '2024-04-01',
            '2024-06-30',
            [$vehicle->id],
        );

        self::assertSame([], $conflicts);
    }

    #[Test]
    public function periodes_chevauchantes_vehicules_disjoints_sans_conflit(): void
    {
        $company = Company::factory()->create();
        $vehicleA = Vehicle::factory()->create();
        $vehicleB = Vehicle::factory()->create();

        // Existante : véhicule A sur S1 2024.
        RentalDiscount::factory()
            ->forCompany($company)
            ->withPeriod(CarbonImmutable::create(2024, 1, 1), CarbonImmutable::create(2024, 6, 30))
            ->appliesToVehicles([$vehicleA])
            ->create();

        // Candidate : véhicule B sur S1 2024 (chevauche en période, pas en véhicules).
        $conflicts = $this->service->findOverlapping(
            $company->id,
            '2024-01-01',
            '2024-06-30',
            [$vehicleB->id],
        );

        self::assertSame([], $conflicts);
    }

    #[Test]
    public function periodes_chevauchantes_intersection_vehicules_genere_conflit(): void
    {
        $company = Company::factory()->create();
        $vehicleA = Vehicle::factory()->create();
        $vehicleB = Vehicle::factory()->create();
        $vehicleC = Vehicle::factory()->create();

        // Existante : véhicules {A, B} sur S1 2024.
        $existing = RentalDiscount::factory()
            ->forCompany($company)
            ->withPeriod(CarbonImmutable::create(2024, 1, 1), CarbonImmutable::create(2024, 6, 30))
            ->appliesToVehicles([$vehicleA, $vehicleB])
            ->create();

        // Candidate : véhicules {B, C} sur S2 (chevauche en juin sur véhicule B).
        $conflicts = $this->service->findOverlapping(
            $company->id,
            '2024-06-15',
            '2024-12-31',
            [$vehicleB->id, $vehicleC->id],
        );

        self::assertCount(1, $conflicts);
        self::assertSame($existing->id, $conflicts[0]->id);
    }

    #[Test]
    public function existante_tous_vehicules_bloque_candidate_subset(): void
    {
        $company = Company::factory()->create();
        $vehicleA = Vehicle::factory()->create();

        // Existante : pivot vide = "tous les véhicules" sur S1 2024.
        $existing = RentalDiscount::factory()
            ->forCompany($company)
            ->withPeriod(CarbonImmutable::create(2024, 1, 1), CarbonImmutable::create(2024, 6, 30))
            ->create();

        // Candidate : véhicule A sur février.
        $conflicts = $this->service->findOverlapping(
            $company->id,
            '2024-02-01',
            '2024-02-28',
            [$vehicleA->id],
        );

        self::assertCount(1, $conflicts);
        self::assertSame($existing->id, $conflicts[0]->id);
    }

    #[Test]
    public function existante_tous_et_candidate_tous_chevauchent(): void
    {
        $company = Company::factory()->create();

        // Existante : tous véhicules, T1.
        $existing = RentalDiscount::factory()
            ->forCompany($company)
            ->withPeriod(CarbonImmutable::create(2024, 1, 1), CarbonImmutable::create(2024, 3, 31))
            ->create();

        // Candidate : tous véhicules, février (chevauche).
        $conflicts = $this->service->findOverlapping(
            $company->id,
            '2024-02-01',
            '2024-04-30',
            [],
        );

        self::assertCount(1, $conflicts);
        self::assertSame($existing->id, $conflicts[0]->id);
    }

    #[Test]
    public function bornes_inclusives_jour_voisin_pas_de_conflit(): void
    {
        $company = Company::factory()->create();
        $vehicle = Vehicle::factory()->create();

        // Existante : se termine le 31/03.
        RentalDiscount::factory()
            ->forCompany($company)
            ->withPeriod(CarbonImmutable::create(2024, 1, 1), CarbonImmutable::create(2024, 3, 31))
            ->appliesToVehicles([$vehicle])
            ->create();

        // Candidate : commence le 01/04 (jour suivant strict).
        $conflicts = $this->service->findOverlapping(
            $company->id,
            '2024-04-01',
            '2024-06-30',
            [$vehicle->id],
        );

        self::assertSame([], $conflicts);
    }

    #[Test]
    public function bornes_inclusives_meme_jour_genere_conflit(): void
    {
        $company = Company::factory()->create();
        $vehicle = Vehicle::factory()->create();

        // Existante : se termine le 31/03.
        RentalDiscount::factory()
            ->forCompany($company)
            ->withPeriod(CarbonImmutable::create(2024, 1, 1), CarbonImmutable::create(2024, 3, 31))
            ->appliesToVehicles([$vehicle])
            ->create();

        // Candidate : commence le 31/03 (même jour = inclusif).
        $conflicts = $this->service->findOverlapping(
            $company->id,
            '2024-03-31',
            '2024-06-30',
            [$vehicle->id],
        );

        self::assertCount(1, $conflicts);
    }

    #[Test]
    public function exclude_id_ignore_la_meme_reduction_en_edition(): void
    {
        $company = Company::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $existing = RentalDiscount::factory()
            ->forCompany($company)
            ->withPeriod(CarbonImmutable::create(2024, 1, 1), CarbonImmutable::create(2024, 6, 30))
            ->appliesToVehicles([$vehicle])
            ->create();

        // Même période, même véhicule, mais on simule une édition de
        // cette même réduction → excludeId la fait disparaître des
        // candidats à conflit.
        $conflicts = $this->service->findOverlapping(
            $company->id,
            '2024-01-01',
            '2024-06-30',
            [$vehicle->id],
            $existing->id,
        );

        self::assertSame([], $conflicts);
    }

    #[Test]
    public function reductions_dautres_entreprises_jamais_en_conflit(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $vehicle = Vehicle::factory()->create();

        // Existante chez l'entreprise A.
        RentalDiscount::factory()
            ->forCompany($companyA)
            ->withPeriod(CarbonImmutable::create(2024, 1, 1), CarbonImmutable::create(2024, 12, 31))
            ->appliesToVehicles([$vehicle])
            ->create();

        // Candidate chez l'entreprise B, même période, même véhicule.
        $conflicts = $this->service->findOverlapping(
            $companyB->id,
            '2024-01-01',
            '2024-12-31',
            [$vehicle->id],
        );

        self::assertSame([], $conflicts);
    }

    #[Test]
    public function reductions_soft_deletees_ignorees(): void
    {
        $company = Company::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $deleted = RentalDiscount::factory()
            ->forCompany($company)
            ->withPeriod(CarbonImmutable::create(2024, 1, 1), CarbonImmutable::create(2024, 6, 30))
            ->appliesToVehicles([$vehicle])
            ->create();
        $deleted->delete(); // soft-delete

        // Candidate chevauche la soft-deletée → pas de conflit.
        $conflicts = $this->service->findOverlapping(
            $company->id,
            '2024-01-01',
            '2024-06-30',
            [$vehicle->id],
        );

        self::assertSame([], $conflicts);
    }

    #[Test]
    public function assert_no_conflict_throw_si_chevauchement(): void
    {
        $company = Company::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $existing = RentalDiscount::factory()
            ->forCompany($company)
            ->withPeriod(CarbonImmutable::create(2024, 1, 1), CarbonImmutable::create(2024, 6, 30))
            ->appliesToVehicles([$vehicle])
            ->create([
                'label' => 'Remise existante',
            ]);

        $caught = null;
        try {
            $this->service->assertNoConflict(
                $company->id,
                '2024-03-01',
                '2024-09-30',
                [$vehicle->id],
            );
        } catch (RentalDiscountOverlapException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught);
        self::assertCount(1, $caught->conflicts);
        self::assertSame($existing->id, $caught->conflicts[0]['id']);
        self::assertStringContainsString('Remise existante', $caught->getUserMessage());
    }

    #[Test]
    public function assert_no_conflict_passthrough_si_pas_de_chevauchement(): void
    {
        $this->expectNotToPerformAssertions();

        $company = Company::factory()->create();

        // Aucune réduction préexistante → passe-plat silencieux,
        // pas d'exception levée. Le test échouerait sur exception.
        $this->service->assertNoConflict(
            $company->id,
            '2024-01-01',
            '2024-12-31',
            [],
        );
    }
}
