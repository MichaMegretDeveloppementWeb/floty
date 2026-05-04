<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Vehicle;

use App\Enums\Vehicle\VehicleExitReason;
use App\Enums\Vehicle\VehicleStatus;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use App\Services\Vehicle\VehicleQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Vérifie l'orchestration repo + agrégateur fiscal + mapping DTO du
 * service `VehicleQueryService` post-migration vers les Repositories.
 */
final class VehicleQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    private VehicleQueryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(VehicleQueryService::class);
    }

    #[Test]
    public function list_for_fleet_view_renvoie_un_dto_par_vehicule_avec_cout_plein_annee(): void
    {
        $year = (int) config('floty.fiscal.available_years')[0];
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create(['vehicle_id' => $vehicle->id]);

        // Pas besoin d'attribution pour fullYearTax - c'est un montant
        // théorique pleine année, indépendant des attributions.
        $result = $this->service->listForFleetView($year);

        $items = $result->toArray();
        self::assertCount(1, $items);
        self::assertSame($vehicle->id, $items[0]['id']);
        self::assertSame($vehicle->license_plate, $items[0]['licensePlate']);
        self::assertGreaterThan(0.0, $items[0]['fullYearTax']);
        self::assertGreaterThan(0.0, $items[0]['dailyTaxRate']);
        // Pro-rata : dailyTaxRate ≈ fullYearTax / 366 (à 1 cent près)
        self::assertEqualsWithDelta(
            $items[0]['fullYearTax'] / 366,
            $items[0]['dailyTaxRate'],
            0.01,
        );
    }

    #[Test]
    public function list_for_options_inclut_les_vehicules_sortis_avec_is_exited_marque(): void
    {
        // Cf. ADR-0018 § 4 + chantier E.5 : le picker véhicule des
        // formulaires Contrats inclut les véhicules retirés pour
        // permettre la consultation et l'édition rétroactive ; le
        // frontend distingue actifs/retirés via `isExited`.
        Vehicle::factory()->create(['exit_date' => null]);
        Vehicle::factory()->create([
            'exit_date' => '2024-01-15',
            'exit_reason' => VehicleExitReason::Sold,
            'current_status' => VehicleStatus::Sold,
        ]);

        $items = $this->service->listForOptions()->toArray();

        self::assertCount(2, $items);

        $exited = array_values(array_filter($items, fn (array $i): bool => $i['isExited'] === true));
        $active = array_values(array_filter($items, fn (array $i): bool => $i['isExited'] === false));

        self::assertCount(1, $exited);
        self::assertCount(1, $active);
        self::assertSame('2024-01-15', $exited[0]['exitDate']);
        self::assertNull($active[0]['exitDate']);
    }
}
