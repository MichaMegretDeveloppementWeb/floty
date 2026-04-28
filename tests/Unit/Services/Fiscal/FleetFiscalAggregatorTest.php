<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Fiscal;

use App\DTO\Fiscal\AnnualCumulByPair;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use App\Services\Fiscal\FleetFiscalAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests des méthodes ajoutées par 04.A.bis pour la page Show :
 * `vehicleFullYearTax` et `vehicleAnnualTaxBreakdownByCompany`.
 */
final class FleetFiscalAggregatorTest extends TestCase
{
    use RefreshDatabase;

    private FleetFiscalAggregator $aggregator;

    private const int YEAR = 2024;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aggregator = $this->app->make(FleetFiscalAggregator::class);
    }

    #[Test]
    public function vehicle_full_year_tax_retourne_le_montant_sans_prorata(): void
    {
        $vehicle = $this->makeVehicleWltp100Essence();

        $fullYearTax = $this->aggregator->vehicleFullYearTax($vehicle, self::YEAR);

        // WLTP 100 g/km essence M1 cat 1 = 173 € CO₂ + 100 € polluants = 273 €
        // (sans prorata, sans LCD — le contexte est daysAssigned = daysInYear).
        self::assertSame(273.0, $fullYearTax);
    }

    #[Test]
    public function vehicle_annual_tax_breakdown_retourne_une_ligne_par_entreprise(): void
    {
        $vehicle = $this->makeVehicleWltp100Essence();

        $cumul = new AnnualCumulByPair([
            $vehicle->id.'|10' => 100,
            $vehicle->id.'|20' => 200,
        ]);

        $breakdown = $this->aggregator->vehicleAnnualTaxBreakdownByCompany(
            $vehicle,
            $cumul,
            self::YEAR,
        );

        self::assertCount(2, $breakdown);

        $byCompany = collect($breakdown)->keyBy('companyId');

        self::assertSame(100, $byCompany[10]['days']);
        self::assertGreaterThan(0.0, $byCompany[10]['taxCo2']);
        self::assertGreaterThan(0.0, $byCompany[10]['taxPollutants']);
        self::assertEqualsWithDelta(
            $byCompany[10]['taxCo2'] + $byCompany[10]['taxPollutants'],
            $byCompany[10]['taxTotal'],
            0.01,
        );

        self::assertSame(200, $byCompany[20]['days']);
        self::assertGreaterThan(
            $byCompany[10]['taxTotal'],
            $byCompany[20]['taxTotal'],
            'Plus de jours = plus de taxe totale (prorata).',
        );
    }

    #[Test]
    public function vehicle_annual_tax_breakdown_renvoie_vide_si_aucune_attribution(): void
    {
        $vehicle = $this->makeVehicleWltp100Essence();

        $breakdown = $this->aggregator->vehicleAnnualTaxBreakdownByCompany(
            $vehicle,
            new AnnualCumulByPair([]),
            self::YEAR,
        );

        self::assertSame([], $breakdown);
    }

    private function makeVehicleWltp100Essence(): Vehicle
    {
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2024-01-01',
            'effective_to' => null,
            'reception_category' => 'M1',
            'vehicle_user_type' => 'VP',
            'energy_source' => 'gasoline',
            'euro_standard' => 'euro_6',
            'pollutant_category' => 'category_1',
            'homologation_method' => 'WLTP',
            'co2_wltp' => 100,
            'co2_nedc' => null,
            'taxable_horsepower' => null,
        ]);

        return $vehicle->fresh(['fiscalCharacteristics']);
    }
}
