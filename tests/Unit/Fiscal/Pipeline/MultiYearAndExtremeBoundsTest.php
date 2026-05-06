<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\Pipeline;

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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests Part A.4 — couvre deux trous identifiés par l'audit :
 *
 *  1. **Cross-year sur 3 années civiles** : `MultiYearProrataTest` couvre
 *     déjà 2 années (2024 + 2025). Ce test étend à 3 (2023 + 2024 + 2025)
 *     pour vérifier qu'aucun jour ne fuit entre années intermédiaires.
 *  2. **Bornes CO₂ WLTP extrêmes (250 g, 300 g)** : la dernière tranche
 *     du barème R-2024-010 est ouverte (≥ 175 g, marginal 65 €/g). Les
 *     tests existants la touchent à 200 g maximum. Vérification que le
 *     calcul reste linéaire au-delà sans saturation cachée.
 */
final class MultiYearAndExtremeBoundsTest extends TestCase
{
    use RefreshDatabase;

    private FiscalCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = $this->app->make(FiscalCalculator::class);
    }

    #[Test]
    public function contrat_traversant_3_annees_civiles_se_borne_correctement_a_chaque_annee(): void
    {
        // Contrat du 2023-12-15 au 2025-02-28 :
        //   - 2023 : 17 jours (15 → 31 décembre)
        //   - 2024 : 366 jours (année bissextile complète)
        //   - 2025 : 59 jours (1er janvier → 28 février)
        // Total brut = 442 jours.
        //
        // Note : seule l'année 2024 est supportée par le moteur fiscal
        // (cf. `app/Fiscal/Year2024/Year2024Boot.php`, autres années à
        // venir chantier δ Phase 11). On teste donc :
        //   - le borneur `Contract::expandToDaysInYear()` pour les 3
        //     années (pur, sans pipeline)
        //   - le pipeline complet sur 2024 (année supportée)
        $vehicle = $this->makeVehicleWltp(co2: 100, vfcStartsAt: '2022-01-01');
        $contract = $this->syntheticContract($vehicle, '2023-12-15', '2025-02-28');

        // Borneur pur — pas de pipeline, juste expansion mathématique
        $days2023 = $contract->expandToDaysInYear(2023);
        $days2024 = $contract->expandToDaysInYear(2024);
        $days2025 = $contract->expandToDaysInYear(2025);

        $this->assertCount(17, $days2023, '2023 : 15→31 déc');
        $this->assertCount(366, $days2024, '2024 entièrement couvert (bissextile)');
        $this->assertCount(59, $days2025, '2025 : 1er jan→28 fév');

        $this->assertSame('2023-12-15', $days2023[0]);
        $this->assertSame('2023-12-31', $days2023[16]);
        $this->assertSame('2024-01-01', $days2024[0]);
        $this->assertSame('2024-12-31', $days2024[365]);
        $this->assertSame('2025-01-01', $days2025[0]);
        $this->assertSame('2025-02-28', $days2025[58]);

        // Sanity : aucun jour fantôme. Total = 442 jours.
        $this->assertSame(
            442,
            count($days2023) + count($days2024) + count($days2025),
            'la somme des jours par année doit égaler la durée brute du contrat',
        );

        // Pipeline 2024 (seule année supportée) : 366 jours, tarif plein
        $r2024 = $this->calculator->calculate($vehicle, [$contract], [], 2024);
        $this->assertSame(366, $r2024->daysAssigned);
        $this->assertSame(366, $r2024->daysInYear);
        $this->assertSame(173.0, $r2024->co2FullYearTariff);
        // Prorata = 366/366 = 1.0 → tarif plein
        $this->assertSame(173.0, $r2024->co2Due);
    }

    #[Test]
    public function bornes_co2_extremes_250g_calcule_lineairement_dans_le_dernier_palier(): void
    {
        // Calcul attendu (barème R-2024-010 cf. CIBS L. 421-120) :
        //   ( 14g × 0)  +  (41g × 1)  +  ( 8g × 2)  +  (32g × 3) +
        //   (20g × 4)   +  (20g × 10) +  (20g × 50) +  (20g × 60) +
        //   (75g × 65)  =  0 + 41 + 16 + 96 + 80 + 200 + 1000 + 1200 + 4875
        //              = 7508 €
        $vehicle = $this->makeVehicleWltp(co2: 250, vfcStartsAt: '2024-01-01');
        $contract = $this->syntheticContract($vehicle, '2024-01-01', '2024-12-31');

        $r = $this->calculator->calculate($vehicle, [$contract], [], 2024);

        $this->assertSame(7508.0, $r->co2FullYearTariff,
            '250 g WLTP = 7508 € plein (75 g dans la tranche ouverte ≥ 175 g à 65 €/g)');
    }

    #[Test]
    public function bornes_co2_extremes_300g_calcule_lineairement_au_dela_du_dernier_palier(): void
    {
        // Pareil que 250 g mais 125 g dans la tranche ouverte :
        //   somme inchangée jusqu'à 175 g + (125 × 65) = 2633 + 8125 = 10758 €
        $vehicle = $this->makeVehicleWltp(co2: 300, vfcStartsAt: '2024-01-01');
        $contract = $this->syntheticContract($vehicle, '2024-01-01', '2024-12-31');

        $r = $this->calculator->calculate($vehicle, [$contract], [], 2024);

        $this->assertSame(10758.0, $r->co2FullYearTariff,
            '300 g WLTP = 10758 € plein (125 g dans la tranche ouverte ≥ 175 g à 65 €/g)');
    }

    #[Test]
    public function difference_300g_moins_250g_egale_50g_fois_marginal_65(): void
    {
        // Invariant marginal : passer de 250 à 300 g doit ajouter
        // exactement 50 × 65 = 3250 € (puisque les deux tombent dans la
        // tranche ouverte ≥ 175 g). Toute déviation = saturation cachée
        // ou mauvais tarif marginal.
        $v250 = $this->makeVehicleWltp(co2: 250, vfcStartsAt: '2024-01-01');
        $v300 = $this->makeVehicleWltp(co2: 300, vfcStartsAt: '2024-01-01');

        $r250 = $this->calculator->calculate(
            $v250,
            [$this->syntheticContract($v250, '2024-01-01', '2024-12-31')],
            [],
            2024,
        );
        $r300 = $this->calculator->calculate(
            $v300,
            [$this->syntheticContract($v300, '2024-01-01', '2024-12-31')],
            [],
            2024,
        );

        $this->assertSame(
            3250.0,
            $r300->co2FullYearTariff - $r250->co2FullYearTariff,
            'marginal exact 50 g × 65 €/g = 3250 € entre 250 g et 300 g',
        );
    }

    // --- Helpers --------------------------------------------------------

    private function makeVehicleWltp(int $co2, string $vfcStartsAt): Vehicle
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
            'effective_from' => Carbon::parse($vfcStartsAt),
            'effective_to' => null,
            'reception_category' => ReceptionCategory::M1,
            'vehicle_user_type' => VehicleUserType::PassengerCar,
            'body_type' => BodyType::InteriorDriving,
            'seats_count' => 5,
            'energy_source' => EnergySource::Gasoline,
            'euro_standard' => EuroStandard::Euro6,
            'pollutant_category' => PollutantCategory::Category1,
            'homologation_method' => HomologationMethod::Wltp,
            'co2_wltp' => $co2,
            'taxable_horsepower' => 6,
            'handicap_access' => false,
            'change_reason' => FiscalCharacteristicsChangeReason::InitialCreation,
        ]);

        return $vehicle->fresh();
    }

    private function syntheticContract(Vehicle $vehicle, string $start, string $end): Contract
    {
        $contract = new Contract;
        $contract->setRawAttributes([
            'vehicle_id' => $vehicle->id,
            'company_id' => 0,
            'start_date' => $start,
            'end_date' => $end,
            'contract_reference' => null,
            'contract_type' => ContractType::Lld->value,
            'notes' => null,
        ], true);

        return $contract;
    }

    private static int $plateCounter = 0;

    private function nextPlate(): string
    {
        $n = ++self::$plateCounter;

        return sprintf('MYE-%03d-MYE', $n);
    }
}
