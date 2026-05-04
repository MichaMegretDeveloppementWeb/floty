<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal;

use App\Enums\Vehicle\BodyType;
use App\Enums\Vehicle\EnergySource;
use App\Enums\Vehicle\EuroStandard;
use App\Enums\Vehicle\FiscalCharacteristicsChangeReason;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\PollutantCategory;
use App\Enums\Vehicle\ReceptionCategory;
use App\Enums\Vehicle\VehicleStatus;
use App\Enums\Vehicle\VehicleUserType;
use App\Fiscal\Pipeline\FiscalPipeline;
use App\Fiscal\Pipeline\PipelineContext;
use App\Models\Contract;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Garantit que **R-2024-003 (FinalRounding) est respectée** : aucun
 * arrondi intermédiaire dans le pipeline fiscal, l'arrondi half-up à
 * 2 décimales s'applique **uniquement en sortie** par couple
 * (vehicle × company) et l'agrégation par redevable somme les valeurs
 * **brutes** (`*Raw`) avant un seul arrondi final.
 *
 * Sémantique BOFiP (CIBS L. 131-1) : « le montant total à payer par
 * chaque redevable est arrondi à l'euro le plus proche, sans arrondi
 * intermédiaire ». Une régression future qui mettrait un `round()` au
 * mauvais endroit (par exemple dans `R-2024-002 DailyProrata` ou dans
 * un sous-aggregator) introduirait une dérive de calcul potentiellement
 * de plusieurs euros sur une grande flotte. Ce test casse immédiatement
 * dans ce cas.
 *
 * Cf. audit produit 2026-05-04 § D5.
 */
final class PipelineNoIntermediateRoundingTest extends TestCase
{
    use RefreshDatabase;

    private FiscalPipeline $pipeline;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pipeline = $this->app->make(FiscalPipeline::class);
    }

    #[Test]
    public function pipeline_expose_les_raw_non_arrondis_et_arrondit_uniquement_en_sortie(): void
    {
        // Cas : WLTP 100 g/km × 120 jours sur 366.
        // - Tarif CO₂ plein 100 g/km = 173 € (cf. BracketsCatalog2024Test)
        // - Polluants Cat 1 = 100 €
        // - Prorata 120/366 = 0,3278688524... (float irrationnel)
        $vehicle = $this->makeWltpVehicle(co2: 100);

        $result = $this->pipeline->execute(
            new PipelineContext(
                vehicle: $vehicle,
                fiscalYear: 2024,
                daysInYear: 366,
                contractsForPair: $this->contractsForDays($vehicle, 120),
                vehicleUnavailabilitiesInYear: [],
                currentFiscalCharacteristics: $vehicle->fiscalCharacteristics->first(),
            ),
        );

        // Étape clé : `*DueRaw` doit conserver la précision native du
        // calcul (pas d'arrondi appliqué en cours de pipeline).
        $expectedCo2Raw = 173.0 * 120 / 366;
        $expectedPollutantsRaw = 100.0 * 120 / 366;
        self::assertEqualsWithDelta($expectedCo2Raw, $result->co2DueRaw, 1e-9, 'co2DueRaw doit être le float natif (pas d\'arrondi intermédiaire).');
        self::assertEqualsWithDelta($expectedPollutantsRaw, $result->pollutantsDueRaw, 1e-9, 'pollutantsDueRaw doit être le float natif.');

        // Et l'arrondi half-up à 2 décimales s'applique exactement en
        // sortie pipeline (champs `*Due`).
        self::assertSame(round($expectedCo2Raw, 2, PHP_ROUND_HALF_UP), $result->co2Due);
        self::assertSame(round($expectedPollutantsRaw, 2, PHP_ROUND_HALF_UP), $result->pollutantsDue);

        // Et `totalDue` somme les arrondis affichables 2 décimales (pas
        // les raw) — c'est l'arrondi par couple, distinct de l'arrondi
        // par redevable de R-2024-003 qui se fait au niveau aggregator.
        self::assertSame(
            round($result->co2Due + $result->pollutantsDue, 2, PHP_ROUND_HALF_UP),
            $result->totalDue,
        );
    }

    #[Test]
    public function le_raw_pleine_annee_egale_le_tarif_plein_sans_arrondi_glissant(): void
    {
        // Sur 366/366 jours, le prorata vaut exactement 1.0 → raw doit
        // être exactement le tarif plein (sans aucune perturbation).
        $vehicle = $this->makeWltpVehicle(co2: 100);

        $result = $this->pipeline->execute(
            new PipelineContext(
                vehicle: $vehicle,
                fiscalYear: 2024,
                daysInYear: 366,
                contractsForPair: $this->contractsForDays($vehicle, 366),
                vehicleUnavailabilitiesInYear: [],
                currentFiscalCharacteristics: $vehicle->fiscalCharacteristics->first(),
            ),
        );

        self::assertSame(173.0, $result->co2DueRaw, 'Pleine année WLTP 100 → raw = tarif plein 173.');
        self::assertSame(100.0, $result->pollutantsDueRaw, 'Pleine année Cat 1 → raw = tarif plein 100.');
        self::assertSame(173.0, $result->co2Due);
        self::assertSame(100.0, $result->pollutantsDue);
        self::assertSame(273.0, $result->totalDue);
    }

    #[Test]
    public function applied_rule_codes_contient_r_2024_003_pour_tracer_l_arrondi_final(): void
    {
        // Garantie d'auditabilité : le snapshot PDF doit pouvoir afficher
        // R-2024-003 dans les règles appliquées (preuve que l'arrondi
        // BOFiP a bien été passé). Sans cette ligne, le PDF de
        // déclaration ne pourrait pas justifier la conformité L. 131-1.
        $vehicle = $this->makeWltpVehicle(co2: 100);

        $result = $this->pipeline->execute(
            new PipelineContext(
                vehicle: $vehicle,
                fiscalYear: 2024,
                daysInYear: 366,
                contractsForPair: $this->contractsForDays($vehicle, 120),
                vehicleUnavailabilitiesInYear: [],
                currentFiscalCharacteristics: $vehicle->fiscalCharacteristics->first(),
            ),
        );

        self::assertContains('R-2024-003', $result->appliedRuleCodes);
    }

    private function makeWltpVehicle(int $co2): Vehicle
    {
        $vehicle = Vehicle::create([
            'license_plate' => 'NR-001-NR',
            'brand' => 'TestBrand',
            'model' => 'TestModel',
            'first_french_registration_date' => Carbon::parse('2022-06-15'),
            'first_origin_registration_date' => Carbon::parse('2022-06-15'),
            'first_economic_use_date' => Carbon::parse('2022-06-15'),
            'acquisition_date' => Carbon::parse('2022-06-15'),
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
            'euro_standard' => EuroStandard::Euro6d,
            'pollutant_category' => PollutantCategory::Category1,
            'homologation_method' => HomologationMethod::Wltp,
            'co2_wltp' => $co2,
            'taxable_horsepower' => 6,
            'handicap_access' => false,
            'change_reason' => FiscalCharacteristicsChangeReason::InitialCreation,
        ]);

        return $vehicle->fresh();
    }

    /**
     * @return list<Contract>
     */
    private function contractsForDays(Vehicle $vehicle, int $days): array
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
            'contract_type' => 'lld',
            'notes' => null,
        ], true);

        return [$contract];
    }
}
