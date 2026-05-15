<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Fiscal;

use App\DTO\Fiscal\ContractsByPair;
use App\Enums\Contract\ContractType;
use App\Models\Company;
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
        // (chantier dette VFC L3 · cohérence affichage par segment).
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

    /**
     * Lot 3 D05 · garantit que la mémoïsation `$fullYearBreakdownCache`
     * retourne strictement la même instance DTO sur appels répétés pour un
     * même couple `(vehicle, year)` · prouve que le cache hit, sans
     * re-exécution du pipeline. Test de comportement, pas d'équivalence
     * sémantique (cette dernière étant déjà couverte par les autres tests).
     */
    #[Test]
    public function vehicle_full_year_tax_breakdown_est_memoise_sur_appels_repetes(): void
    {
        $vehicle = $this->makeVehicleWltp100Essence();

        $first = $this->aggregator->vehicleFullYearTaxBreakdown($vehicle, self::YEAR);
        $second = $this->aggregator->vehicleFullYearTaxBreakdown($vehicle, self::YEAR);

        // Identité d'instance · le cache renvoie le DTO précédemment construit
        self::assertSame($first, $second);
    }

    /**
     * Lot 3 D05 · garantit que le cache discrimine bien sur le couple
     * `(vehicleId, year)` · 2 véhicules distincts ou 2 années distinctes
     * doivent produire 2 instances différentes (pas de cross-pollution).
     */
    #[Test]
    public function vehicle_full_year_tax_breakdown_distingue_par_couple_vehicule_annee(): void
    {
        $vehicleA = $this->makeVehicleWltp100Essence();
        $vehicleB = $this->makeVehicleWltp100Essence();

        $a2024 = $this->aggregator->vehicleFullYearTaxBreakdown($vehicleA, self::YEAR);
        $b2024 = $this->aggregator->vehicleFullYearTaxBreakdown($vehicleB, self::YEAR);

        // Instances distinctes · 2 véhicules différents = 2 entrées cache
        self::assertNotSame($a2024, $b2024);
        // Mais valeurs équivalentes (mêmes caractéristiques fiscales)
        self::assertSame($a2024->total, $b2024->total);
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

    /**
     * Bloc Φ.bis · vérifie qu'un contrat à cheval sur la scission
     * polluants du 01/03/2026 (LF 2026 art. 58 V IV, +30 %) expose
     * 2 segments distincts dans son breakdown · le premier avec tarif
     * Cat1 100 €/an (R-2026-014), le second avec tarif Cat1 130 €/an
     * (R-2026-014-bis). Garantit la transparence pédagogique de l'UI
     * fiche contrat.
     */
    #[Test]
    public function contract_tax_breakdown_expose_2_segments_pour_scission_polluants_2026(): void
    {
        $vehicle = $this->makeVehicleWltp100EssenceFor(2026);
        $company = Company::factory()->create();
        // Contrat 15/01/2026 → 24/04/2026 · 100 jours, à cheval 01/03.
        $contract = Contract::create([
            'vehicle_id' => $vehicle->id,
            'company_id' => $company->id,
            'start_date' => '2026-01-15',
            'end_date' => '2026-04-24',
            'contract_reference' => null,
            'contract_type' => ContractType::Lld->value,
        ]);

        $breakdown = $this->aggregator->contractTaxBreakdown($contract, []);

        self::assertCount(1, $breakdown->years);
        $year = $breakdown->years[0];
        self::assertSame(2026, $year->year);
        self::assertSame(100, $year->daysAssigned);

        // 2 segments dans l'année · scission au 01/03/2026.
        self::assertCount(2, $year->segments);

        // Segment 1 · 01/01-28/02 · R-2026-014 · Cat1 = 100 €/an.
        $seg1 = $year->segments[0];
        self::assertSame('2026-01-01', $seg1->effectiveFromInYear);
        self::assertSame('2026-02-28', $seg1->effectiveToInYear);
        self::assertSame(45, $seg1->daysAssignedToContract); // 15/01 → 28/02
        self::assertSame(100.0, $seg1->pollutantsFullYearTariff);
        self::assertSame(12.33, $seg1->pollutantsDue); // 100 × 45/365
        self::assertContains('R-2026-014', $seg1->appliedRuleCodes);

        // Segment 2 · 01/03-... · R-2026-014-bis · Cat1 = 130 €/an.
        $seg2 = $year->segments[1];
        self::assertSame('2026-03-01', $seg2->effectiveFromInYear);
        self::assertSame(55, $seg2->daysAssignedToContract); // 01/03 → 24/04
        self::assertSame(130.0, $seg2->pollutantsFullYearTariff);
        self::assertSame(19.59, $seg2->pollutantsDue); // 130 × 55/365
        self::assertContains('R-2026-014-bis', $seg2->appliedRuleCodes);

        // Total année · somme cohérente des 2 segments.
        self::assertSame(31.92, $year->pollutantsDue); // 12,33 + 19,59
        // CO₂ pas scindé en 2026 · 213 €/an constant.
        self::assertSame(213.0, $year->co2FullYearTariff);
        self::assertSame(58.36, $year->co2Due); // 213 × 100/365
    }

    private function makeVehicleWltp100EssenceFor(int $year): Vehicle
    {
        $vehicle = Vehicle::factory()->create();
        VehicleFiscalCharacteristics::factory()->create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => sprintf('%d-01-01', $year - 2),
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
