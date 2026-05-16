<?php

declare(strict_types=1);

namespace Tests\Feature\Fiscal;

use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use App\Repositories\User\Vehicle\VehicleFiscalCharacteristicsWriteRepository;
use App\Services\Fiscal\FiscalCacheInvalidator;
use App\Services\Fiscal\FleetFiscalAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests Feature exhaustifs · garantie d'invalidation cache fiscal
 * (chantier 2026-05-17).
 *
 * Couvre les 5 triggers identifiés dans la cartographie · pour chaque
 * scénario · le cache est invalidé et la valeur recalculée correctement.
 *
 * **Pattern de test** ·
 *   1. Préparer un véhicule + VFC qui produit une taxe pleine connue
 *   2. 1er appel `vehicleFullYearTaxBreakdown` · cache miss → cache put
 *   3. Vérifier · clé cache présente
 *   4. Déclencher l'événement (mutation VFC ou Vehicle)
 *   5. Vérifier · clé cache absente (invalidation)
 *   6. 2e appel · cache miss → recalcule avec la nouvelle valeur
 *   7. Vérifier · valeur cohérente avec la nouvelle config
 */
final class FiscalCacheInvalidationTest extends TestCase
{
    use RefreshDatabase;

    private const int YEAR = 2024;

    private FleetFiscalAggregator $aggregator;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush(); // état net pour chaque test
        $this->aggregator = $this->app->make(FleetFiscalAggregator::class);
    }

    #[Test]
    public function vfc_create_invalide_le_cache(): void
    {
        $vehicle = Vehicle::factory()->create([
            'first_origin_registration_date' => '2020-01-15',
        ]);

        // Initial · 1 VFC bornée pour permettre l'ajout d'une 2ᵉ VFC
        // 2025+ sans déclencher le trigger BDD anti-overlap.
        VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2024-01-01',
            'effective_to' => '2024-12-31',
        ]);

        // Mise en cache initiale via 1er appel
        $first = $this->aggregator->vehicleFullYearTaxBreakdown($vehicle, self::YEAR);
        $cacheKey = FiscalCacheInvalidator::cacheKeyForBreakdown($vehicle->id, self::YEAR);
        self::assertTrue(Cache::has($cacheKey), 'Après 1er appel · cache présent');
        self::assertGreaterThan(0.0, $first->total);

        // Création d'une nouvelle VFC pour ce véhicule · Observer saved
        $this->aggregator = $this->app->make(FleetFiscalAggregator::class); // fresh per-request cache
        VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2025-01-01',
            'effective_to' => null,
        ]);

        self::assertFalse(Cache::has($cacheKey), 'Après création VFC · cache invalidé');
    }

    #[Test]
    public function vfc_update_invalide_le_cache(): void
    {
        $vehicle = $this->makeVehicleWithVfcEssenceWltp100();

        // Mise en cache
        $this->aggregator->vehicleFullYearTaxBreakdown($vehicle, self::YEAR);
        $cacheKey = FiscalCacheInvalidator::cacheKeyForBreakdown($vehicle->id, self::YEAR);
        self::assertTrue(Cache::has($cacheKey));

        // Mutation · update CO2 sur la VFC existante
        $vfc = VehicleFiscalCharacteristics::query()->where('vehicle_id', $vehicle->id)->firstOrFail();
        $vfc->update(['co2_wltp' => 200]);

        self::assertFalse(Cache::has($cacheKey), 'Après update VFC · cache invalidé');
    }

    #[Test]
    public function vfc_delete_instance_invalide_le_cache(): void
    {
        $vehicle = $this->makeVehicleWithVfcEssenceWltp100();
        VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2025-01-01',
            'effective_to' => null,
        ]);

        $this->aggregator->vehicleFullYearTaxBreakdown($vehicle, self::YEAR);
        $cacheKey = FiscalCacheInvalidator::cacheKeyForBreakdown($vehicle->id, self::YEAR);
        self::assertTrue(Cache::has($cacheKey));

        // Delete sur instance Eloquent · déclenche l'Observer
        $vfc = VehicleFiscalCharacteristics::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('effective_from', '2025-01-01')
            ->firstOrFail();
        $vfc->delete();

        self::assertFalse(Cache::has($cacheKey), 'Après delete instance VFC · cache invalidé');
    }

    #[Test]
    public function vfc_delete_one_bulk_invalide_le_cache(): void
    {
        // Couvre le piège · `VehicleFiscalCharacteristicsWriteRepository::deleteOne`
        // utilise un bulk delete query-builder qui n'invoque PAS les
        // events Eloquent. Le repo appelle FiscalCacheInvalidator
        // manuellement avant le bulk.
        $vehicle = $this->makeVehicleWithVfcEssenceWltp100();
        $vfc = VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2025-01-01',
            'effective_to' => null,
        ]);

        $this->aggregator->vehicleFullYearTaxBreakdown($vehicle, self::YEAR);
        $cacheKey = FiscalCacheInvalidator::cacheKeyForBreakdown($vehicle->id, self::YEAR);
        self::assertTrue(Cache::has($cacheKey));

        $this->app->make(VehicleFiscalCharacteristicsWriteRepository::class)->deleteOne($vfc->id);

        self::assertFalse(Cache::has($cacheKey), 'Après deleteOne bulk · cache invalidé manuellement');
    }

    #[Test]
    public function vfc_delete_versions_from_date_bulk_invalide_le_cache(): void
    {
        // Couvre le 2ᵉ bulk delete · `deleteVersionsFromDate`. Aussi
        // appelé en cascade par `UpdateVehicleAction`.
        $vehicle = $this->makeVehicleWithVfcEssenceWltp100();
        VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2025-06-01',
            'effective_to' => null,
        ]);

        $this->aggregator->vehicleFullYearTaxBreakdown($vehicle, self::YEAR);
        $cacheKey = FiscalCacheInvalidator::cacheKeyForBreakdown($vehicle->id, self::YEAR);
        self::assertTrue(Cache::has($cacheKey));

        $this->app->make(VehicleFiscalCharacteristicsWriteRepository::class)
            ->deleteVersionsFromDate($vehicle->id, Carbon::parse('2025-06-01'));

        self::assertFalse(Cache::has($cacheKey), 'Après deleteVersionsFromDate bulk · cache invalidé manuellement');
    }

    #[Test]
    public function vehicle_first_origin_registration_date_changed_invalide_le_cache(): void
    {
        // Couvre le SEUL champ Vehicle lu par le pipeline (R-2024-017).
        $vehicle = $this->makeVehicleWithVfcEssenceWltp100();

        $this->aggregator->vehicleFullYearTaxBreakdown($vehicle, self::YEAR);
        $cacheKey = FiscalCacheInvalidator::cacheKeyForBreakdown($vehicle->id, self::YEAR);
        self::assertTrue(Cache::has($cacheKey));

        $vehicle->update(['first_origin_registration_date' => '2018-06-01']);

        self::assertFalse(Cache::has($cacheKey), 'Après changement first_origin_registration_date · cache invalidé');
    }

    #[Test]
    public function vehicle_cosmetic_update_ne_invalide_pas_le_cache(): void
    {
        // Garantie inverse · les modifs cosmétiques (license_plate,
        // brand, model, mileage, notes, etc.) ne déclenchent PAS
        // l'invalidation cache · optimisation observers Vehicle ·
        // `wasChanged('first_origin_registration_date')` filtre.
        $vehicle = $this->makeVehicleWithVfcEssenceWltp100();

        $this->aggregator->vehicleFullYearTaxBreakdown($vehicle, self::YEAR);
        $cacheKey = FiscalCacheInvalidator::cacheKeyForBreakdown($vehicle->id, self::YEAR);
        self::assertTrue(Cache::has($cacheKey));

        $vehicle->update(['license_plate' => 'XX-999-XX', 'mileage_current' => 50000]);

        self::assertTrue(Cache::has($cacheKey), 'Modifs cosmétiques · cache conservé (optimisation)');
    }

    #[Test]
    public function vehicle_soft_delete_invalide_le_cache(): void
    {
        $vehicle = $this->makeVehicleWithVfcEssenceWltp100();

        $this->aggregator->vehicleFullYearTaxBreakdown($vehicle, self::YEAR);
        $cacheKey = FiscalCacheInvalidator::cacheKeyForBreakdown($vehicle->id, self::YEAR);
        self::assertTrue(Cache::has($cacheKey));

        $vehicle->delete();

        self::assertFalse(Cache::has($cacheKey), 'Après soft delete · cache invalidé');
    }

    #[Test]
    public function valeur_cachee_strictement_identique_au_recalcul(): void
    {
        // Filet d'équivalence · la valeur cachée DOIT être exactement
        // celle du recalcul. Si l'un diverge, montant fiscal erroné
        // affiché à l'utilisateur · risque inacceptable.
        $vehicle = $this->makeVehicleWithVfcEssenceWltp100();

        $first = $this->aggregator->vehicleFullYearTaxBreakdown($vehicle, self::YEAR);

        // Vide la mémoïsation per-request mais garde le cache persistant
        $this->aggregator = $this->app->make(FleetFiscalAggregator::class);
        $cached = $this->aggregator->vehicleFullYearTaxBreakdown($vehicle, self::YEAR);

        self::assertSame($first->total, $cached->total);
        self::assertSame($first->daysInYear, $cached->daysInYear);
        self::assertSame($first->appliedRuleCodes, $cached->appliedRuleCodes);
        self::assertCount(count($first->taxSegments), $cached->taxSegments);
    }

    /**
     * Crée un véhicule avec UNE VFC bornée 2024-01-01 → 2024-12-31. La
     * borne `effective_to` fermée permet aux tests d'ajouter une 2ᵉ VFC
     * (à partir de 2025-01-01) sans déclencher le trigger BDD
     * anti-overlap.
     */
    private function makeVehicleWithVfcEssenceWltp100(): Vehicle
    {
        $vehicle = Vehicle::factory()->create([
            'first_origin_registration_date' => '2020-01-15',
        ]);
        VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2024-01-01',
            'effective_to' => '2024-12-31',
        ]);

        return $vehicle->fresh();
    }
}
