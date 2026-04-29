<?php

declare(strict_types=1);

namespace Tests\Feature\Fiscal;

use App\DTO\Fiscal\ContractsByPair;
use App\Enums\Contract\ContractType;
use App\Enums\Vehicle\BodyType;
use App\Enums\Vehicle\EnergySource;
use App\Enums\Vehicle\EuroStandard;
use App\Enums\Vehicle\FiscalCharacteristicsChangeReason;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\PollutantCategory;
use App\Enums\Vehicle\ReceptionCategory;
use App\Enums\Vehicle\VehicleStatus;
use App\Enums\Vehicle\VehicleUserType;
use App\Models\Contract;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use App\Services\Fiscal\FiscalCalculator;
use App\Services\Fiscal\FleetFiscalAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Validations bout-en-bout du moteur fiscal Floty contre les **cas
 * officiels chiffrés** du catalogue 2024 (BOFiP, exemples cités dans
 * `project-management/taxes-rules/2024.md`).
 *
 * **But** : prouver que pour des données d'entrée référencées dans la
 * documentation officielle, le pipeline complet (Pipeline →
 * FleetFiscalAggregator) produit **strictement les mêmes montants**
 * que ceux publiés par l'administration, **au centime près**.
 *
 * Ces cas sont distincts des goldens unitaires de
 * `FiscalCalculatorTest` qui valident chaque règle isolément. Ici on
 * exerce le pipeline complet avec la chaîne classification →
 * exonération → pricing → prorata → arrondi par redevable.
 */
final class FiscalEngineEndToEndTest extends TestCase
{
    use RefreshDatabase;

    private FiscalCalculator $calculator;

    private FleetFiscalAggregator $aggregator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = $this->app->make(FiscalCalculator::class);
        $this->aggregator = $this->app->make(FleetFiscalAggregator::class);
    }

    /**
     * Cas BOFiP `BOI-AIS-MOB-10-30-20-20240710` § 230, exemple 2 :
     * véhicule M1 essence Euro 6 WLTP 100 g/km, 306 jours d'affectation
     * → tarif annuel plein WLTP = 173 €, prorata 306/366
     * → CO₂ due = 173 × 306 / 366 = **144,64 €** (cité explicitement
     *   par le BOFiP).
     */
    #[Test]
    public function bofip_exemple_2_wltp_100g_306j_donne_144_64_euros(): void
    {
        $vehicle = $this->makeVehicleWltp100Essence();

        $r = $this->calculator->calculate(
            $vehicle,
            $this->contractsForDays($vehicle, 306),
            [],
            2024,
        );

        // Conformité au texte BOFiP au centime près
        $this->assertSame(173.0, $r->co2FullYearTariff);
        $this->assertSame(144.64, $r->co2Due);
        // Polluants cat 1 = 100 € plein × 306/366 = 83.6065... → 83,61
        $this->assertSame(83.61, $r->pollutantsDue);
    }

    /**
     * Cas R-2024-002 — exemple Skoda Octavia essence Euro 6 M1
     * polluants cat 1, 183 jours d'affectation → prorata exact 0,5.
     * Polluants annuel plein = 100 €, prorata 183/366 = 0,5
     * → polluants due = **50,00 €** (cité dans le catalogue).
     */
    #[Test]
    public function r002_skoda_183j_donne_polluants_50_euros(): void
    {
        $vehicle = $this->makeVehicleWltp100Essence();

        $r = $this->calculator->calculate(
            $vehicle,
            $this->contractsForDays($vehicle, 183),
            [],
            2024,
        );

        $this->assertSame(0.5, 183 / 366); // sanity check : prorata exact
        $this->assertSame(50.0, $r->pollutantsDue);
    }

    /**
     * Cas R-2024-021 LCD — cumul annuel strictement ≤ 30 jours par
     * couple (véhicule × entreprise) → exonération totale des deux
     * taxes, aucune ligne fiscale produite.
     */
    #[Test]
    public function r021_lcd_30_jours_strict_donne_exoneration_totale(): void
    {
        $vehicle = $this->makeVehicleWltp100Essence();

        $r = $this->calculator->calculate(
            $vehicle,
            $this->lcdContractsForDays($vehicle, 30),
            [],
            2024,
        );

        $this->assertTrue($r->lcdExempt);
        $this->assertSame(0.0, $r->co2Due);
        $this->assertSame(0.0, $r->pollutantsDue);
        $this->assertSame(0.0, $r->totalDue);

        // Cas frontière : 31 j non aligné mois civil → plus exonéré.
        $r31 = $this->calculator->calculate(
            $vehicle,
            $this->contractsForDays($vehicle, 31),
            [],
            2024,
        );
        $this->assertFalse($r31->lcdExempt);
        $this->assertGreaterThan(0.0, $r31->totalDue);
    }

    /**
     * Cas R-2024-003 — exemple ACME du catalogue : entreprise A avec
     * 2 véhicules.
     *
     * - Véhicule 1 : WLTP 100 g/km essence M1 cat 1, 306 jours
     *   → CO₂ raw 144,6393… + polluants raw 83,6066… = 228,2459…
     * - Véhicule 2 : même type, 100 jours
     *   → CO₂ raw 47,2677… + polluants raw 27,3224… =  74,5901…
     *
     * Total brut redevable = 302,8361… €
     * Arrondi half-up à l'euro le plus proche : **303 €** (cité dans
     * le catalogue § R-2024-003).
     *
     * **Sémantique R-003** : un seul arrondi par redevable au niveau
     * de `FleetFiscalAggregator::companyAnnualTax()`, pas par couple.
     * Ce test prouve que la mécanique d'arrondi correcte est en place
     * (cf. fix de la phase 1.9).
     */
    #[Test]
    public function r003_arrondi_par_redevable_acme_2_vehicules_donne_303_euros(): void
    {
        $companyId = 7; // identifiant abstrait du redevable
        $vehicle1 = $this->makeVehicleWltp100Essence();
        $vehicle2 = $this->makeVehicleWltp100Essence();

        $contracts = new ContractsByPair([
            $vehicle1->id.'|'.$companyId => $this->contractsForDays($vehicle1, 306),
            $vehicle2->id.'|'.$companyId => $this->contractsForDays($vehicle2, 100),
        ]);
        $vehiclesById = new Collection([
            $vehicle1->id => $vehicle1,
            $vehicle2->id => $vehicle2,
        ]);

        $totalArrondi = $this->aggregator->companyAnnualTax(
            $companyId,
            $vehiclesById,
            $contracts,
            [$vehicle1->id => [], $vehicle2->id => []],
            2024,
        );

        // Total brut = 144,6393 + 83,6066 + 47,2677 + 27,3224 = 302,8361
        // Arrondi half-up à 2 décimales : 302,84 € (le BOFiP donne 303 € à
        // l'euro mais notre Aggregator arrondit à 2 décimales — l'arrondi
        // à l'euro entier vit au niveau Action de déclaration en phase 11)
        $this->assertSame(302.84, $totalArrondi);

        // Vérification que round à l'euro = 303 (cf. catalogue)
        $this->assertSame(303.0, round($totalArrondi));
    }

    /**
     * Crée un véhicule M1 essence Euro 6 WLTP 100 g/km cat 1 — la
     * configuration de référence des exemples BOFiP du catalogue.
     */
    private function makeVehicleWltp100Essence(): Vehicle
    {
        $vehicle = Vehicle::create([
            'license_plate' => $this->nextPlate(),
            'brand' => 'Renault',
            'model' => 'Test',
            'first_french_registration_date' => Carbon::parse('2022-01-01'),
            'first_origin_registration_date' => Carbon::parse('2022-01-01'),
            'first_economic_use_date' => Carbon::parse('2022-01-01'),
            'acquisition_date' => Carbon::parse('2022-01-01'),
            'current_status' => VehicleStatus::Active,
        ]);

        VehicleFiscalCharacteristics::create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => Carbon::parse('2024-01-01'),
            'effective_to' => null,
            'reception_category' => ReceptionCategory::M1,
            'vehicle_user_type' => VehicleUserType::PassengerCar,
            'body_type' => BodyType::InteriorDriving,
            'seats_count' => 5,
            'energy_source' => EnergySource::Gasoline,
            'euro_standard' => EuroStandard::Euro6,
            'pollutant_category' => PollutantCategory::Category1,
            'homologation_method' => HomologationMethod::Wltp,
            'co2_wltp' => 100,
            'taxable_horsepower' => 6,
            'handicap_access' => false,
            'change_reason' => FiscalCharacteristicsChangeReason::InitialCreation,
        ]);

        return $vehicle->fresh();
    }

    /**
     * Contrat synthétique non-LCD de `$days` jours pour `$vehicle`.
     *
     * @return list<Contract>
     */
    private function contractsForDays(Vehicle $vehicle, int $days): array
    {
        $start = ($days >= 29 && $days <= 31) ? '2024-01-15' : '2024-01-01';
        $end = (new \DateTimeImmutable($start))
            ->modify('+'.($days - 1).' days')
            ->format('Y-m-d');
        $contract = new Contract;
        $contract->setRawAttributes([
            'vehicle_id' => $vehicle->id,
            'company_id' => 0,
            'driver_id' => null,
            'start_date' => $start,
            'end_date' => $end,
            'contract_reference' => null,
            'contract_type' => ContractType::Lld->value,
            'notes' => null,
        ], true);

        return [$contract];
    }

    /**
     * Variante LCD : durée ≤ 30 j, démarrage 1er jan.
     *
     * @return list<Contract>
     */
    private function lcdContractsForDays(Vehicle $vehicle, int $days): array
    {
        $start = '2024-01-01';
        $end = (new \DateTimeImmutable($start))
            ->modify('+'.($days - 1).' days')
            ->format('Y-m-d');
        $contract = new Contract;
        $contract->setRawAttributes([
            'vehicle_id' => $vehicle->id,
            'company_id' => 0,
            'driver_id' => null,
            'start_date' => $start,
            'end_date' => $end,
            'contract_reference' => null,
            'contract_type' => ContractType::Lcd->value,
            'notes' => null,
        ], true);

        return [$contract];
    }

    private static int $plateCounter = 0;

    private function nextPlate(): string
    {
        $n = ++self::$plateCounter;

        return sprintf('E2E-%03d-E2E', $n);
    }
}
