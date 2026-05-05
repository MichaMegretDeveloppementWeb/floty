<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Fiscal;

use App\DTO\Fiscal\ContractsByPair;
use App\Enums\Contract\ContractType;
use App\Models\Contract;
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
        // (sans prorata, sans LCD - le contexte est daysAssigned = daysInYear).
        self::assertSame(273.0, $fullYearTax);
    }

    #[Test]
    public function vehicle_annual_tax_breakdown_retourne_une_ligne_par_entreprise(): void
    {
        $vehicle = $this->makeVehicleWltp100Essence();

        // Contrats non-LCD pour produire des taxes effectives.
        $contractsByPair = new ContractsByPair([
            $vehicle->id.'|10' => [
                $this->syntheticContract($vehicle->id, 10, '2024-01-15', 100),
            ],
            $vehicle->id.'|20' => [
                $this->syntheticContract($vehicle->id, 20, '2024-04-15', 200),
            ],
        ]);

        $breakdown = $this->aggregator->vehicleAnnualTaxBreakdownByCompany(
            $vehicle,
            $contractsByPair,
            [],
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
            new ContractsByPair([]),
            [],
            self::YEAR,
        );

        self::assertSame([], $breakdown);
    }

    /**
     * Contrat synthétique non-persisté de `$days` jours pour un couple
     * donné. Démarre à `$start` ; la durée garantit non-LCD si `$days > 30`
     * et la plage n'est pas un mois civil entier.
     */
    private function syntheticContract(int $vehicleId, int $companyId, string $start, int $days): Contract
    {
        $end = (new \DateTimeImmutable($start))
            ->modify('+'.($days - 1).' days')
            ->format('Y-m-d');

        $contract = new Contract;
        $contract->setRawAttributes([
            'vehicle_id' => $vehicleId,
            'company_id' => $companyId,
            'driver_id' => null,
            'start_date' => $start,
            'end_date' => $end,
            'contract_reference' => null,
            'contract_type' => ContractType::Lld->value,
            'notes' => null,
        ], true);

        return $contract;
    }

    #[Test]
    public function vehicle_full_year_tax_breakdown_renvoie_le_detail_du_calcul(): void
    {
        $vehicle = $this->makeVehicleWltp100Essence();

        $breakdown = $this->aggregator->vehicleFullYearTaxBreakdown($vehicle, self::YEAR);

        // WLTP 100 essence M1 cat 1 = 173 € CO₂ + 100 € polluants = 273 €
        self::assertSame(273.0, $breakdown->total);
        self::assertNotEmpty($breakdown->appliedRuleCodes);

        // Mono-VFC : un seul segment couvrant l'année entière. Les
        // tarifs et méthodes/catégories vivent désormais dans le segment
        // (chantier dette VFC L3 — cohérence affichage par segment).
        self::assertCount(1, $breakdown->taxSegments);
        $segment = $breakdown->taxSegments[0];
        self::assertSame(173.0, $segment->co2FullYearTariff);
        self::assertSame(100.0, $segment->pollutantsFullYearTariff);
        self::assertSame(173.0, $segment->co2Due);
        self::assertSame(100.0, $segment->pollutantsDue);
        self::assertSame('WLTP', $segment->co2Method->value);
        self::assertSame('category_1', $segment->pollutantCategory->value);
        self::assertSame(366, $segment->daysInSegment);
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
