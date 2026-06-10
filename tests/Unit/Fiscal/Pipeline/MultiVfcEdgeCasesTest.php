<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\Pipeline;

use App\Enums\Contract\ContractType;
use App\Enums\Fiscal\RuleSection;
use App\Enums\Fiscal\RuleTab;
use App\Enums\Fiscal\RuleType;
use App\Enums\Fiscal\TaxType;
use App\Enums\Vehicle\BodyType;
use App\Enums\Vehicle\EnergySource;
use App\Enums\Vehicle\EuroStandard;
use App\Enums\Vehicle\FiscalCharacteristicsChangeReason;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\PollutantCategory;
use App\Enums\Vehicle\ReceptionCategory;
use App\Enums\Vehicle\VehicleStatus;
use App\Enums\Vehicle\VehicleUserType;
use App\Fiscal\Contracts\Concerns\RuleActiveByDefaultTrait;
use App\Fiscal\Contracts\PricingRule;
use App\Fiscal\Pipeline\FiscalSegmentedExecutor;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\Pipeline\RuleEffectiveSegmenter;
use App\Fiscal\Registry\FiscalRuleRegistry;
use App\Fiscal\ValueObjects\RulePedagogicalContent;
use App\Fiscal\Year2024\Year2024Boot;
use App\Models\Contract;
use App\Models\Vehicle;
use App\Models\VehicleEvent;
use App\Models\VehicleFiscalCharacteristics;
use App\Services\Shared\Fiscal\FiscalYearContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests Unit Part A.1 · combler les trous identifiés sur multi-VFC ≥3
 * dans une même année (audit confiance 7/10 → 9/10).
 *
 * Les patterns helpers (`makeVehicle`, `vfcCommonFields`, `syntheticContract`,
 * `syntheticVehicleEvent`) sont alignés sur
 * {@see FiscalSegmentedExecutorTest} pour cohérence du style.
 */
final class MultiVfcEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    private FiscalSegmentedExecutor $executor;

    private FiscalYearContext $yearContext;

    protected function setUp(): void
    {
        parent::setUp();
        $this->executor = $this->app->make(FiscalSegmentedExecutor::class);
        $this->yearContext = $this->app->make(FiscalYearContext::class);
    }

    #[Test]
    public function trois_vfc_dans_une_meme_annee_avec_co2_differents_total_correct(): void
    {
        // VFC v1 (jan-avr): 100 g WLTP   → 173 € plein
        // VFC v2 (mai-aoû): 145 g WLTP   → palier supérieur (>135), tarif > v1
        // VFC v3 (sep-déc): 175 g WLTP   → palier encore supérieur
        $vehicle = $this->makeVehicleWithThreeDifferentVfcs(
            v1Co2: 100, v1End: '2024-04-30',
            v2Co2: 145, v2End: '2024-08-31',
            v3Co2: 175,
        );
        $contracts = [$this->syntheticContract($vehicle, '2024-01-01', '2024-12-31', ContractType::Lld)];

        $segmented = $this->executor->execute($this->buildContext($vehicle, $contracts));

        // Sanity : tous les jours de l'année sont comptés
        $this->assertSame(366, $segmented->daysAssigned, 'année 2024 bissextile');

        // Référence de borne haute : si toute l'année tournait à 175 g
        $vehicleHigh = $this->makeVehicleWithSingleVfc(co2: 175);
        $contractsHigh = [$this->syntheticContract($vehicleHigh, '2024-01-01', '2024-12-31', ContractType::Lld)];
        $high = $this->executor->execute($this->buildContext($vehicleHigh, $contractsHigh));

        // Référence de borne basse : si toute l'année tournait à 100 g
        $vehicleLow = $this->makeVehicleWithSingleVfc(co2: 100);
        $contractsLow = [$this->syntheticContract($vehicleLow, '2024-01-01', '2024-12-31', ContractType::Lld)];
        $low = $this->executor->execute($this->buildContext($vehicleLow, $contractsLow));

        $this->assertGreaterThan(
            $low->co2Due,
            $segmented->co2Due,
            'le mélange 3 VFC doit être strictement supérieur au tarif tout-100g',
        );
        $this->assertLessThan(
            $high->co2Due,
            $segmented->co2Due,
            'le mélange 3 VFC doit être strictement inférieur au tarif tout-175g',
        );
    }

    #[Test]
    public function quatre_vfc_dans_une_annee_avec_changement_handicap_au_milieu_isole_l_exoneration_au_segment_concerne(): void
    {
        // VFC v1 (jan-mar): handicap_access=false, 100 g
        // VFC v2 (avr-juin): handicap_access=TRUE, 100 g  → exonéré
        // VFC v3 (juil-sep): handicap_access=TRUE, 100 g  → exonéré
        // VFC v4 (oct-déc): handicap_access=false, 100 g
        // → seuls les segments 1 + 4 (jan-mar + oct-déc) doivent produire de la taxe.
        $vehicle = $this->makeVehicleWithFourVfcsHandicapMiddle();
        $contracts = [$this->syntheticContract($vehicle, '2024-01-01', '2024-12-31', ContractType::Lld)];

        $segmented = $this->executor->execute($this->buildContext($vehicle, $contracts));

        // Référence sans aucun handicap (toute l'année 100 g) → tarif plein
        $vehicleAllNonHandicap = $this->makeVehicleWithSingleVfc(co2: 100);
        $contractsRef = [$this->syntheticContract($vehicleAllNonHandicap, '2024-01-01', '2024-12-31', ContractType::Lld)];
        $allTaxable = $this->executor->execute($this->buildContext($vehicleAllNonHandicap, $contractsRef));

        // Le résultat segmenté doit être strictement inférieur au plein
        // (puisque les segments 2 et 3 sont exonérés handicap).
        $this->assertGreaterThan(
            $segmented->co2Due,
            $allTaxable->co2Due,
            'l\'exonération handicap des segments 2-3 doit ramener la taxe sous le plein-année',
        );
        $this->assertGreaterThan(
            0.0,
            $segmented->co2Due,
            'les segments 1 et 4 (non handicap) doivent produire de la taxe',
        );

        // Vérification volumétrique : les segments handicap sont
        // exonérés mais leurs jours restent comptés dans daysAssigned
        // (le moteur ne masque pas la présence du véhicule, il neutralise
        // seulement la taxe).
        $this->assertSame(366, $segmented->daysAssigned);
    }

    #[Test]
    public function vfc_change_pile_au_pivot_31_decembre_n_isole_pas_de_doublon_de_jour(): void
    {
        // Cas frontière : VFC v1 valide jusqu'au 31/12/2024 (inclus)
        // et VFC v2 démarre le 01/01/2025 → pivot parfait à la frontière
        // d'année. Le calcul 2024 ne doit voir que v1, daysAssigned = 366
        // (pas 367 si v2 fuyait sur le 31/12 par erreur de bord).
        $vehicle = $this->makeVehicleWithPivotVfcAtYearEnd();
        $contracts = [$this->syntheticContract($vehicle, '2024-01-01', '2024-12-31', ContractType::Lld)];

        $result = $this->executor->execute($this->buildContext($vehicle, $contracts));

        $this->assertSame(366, $result->daysAssigned, 'aucun doublon : v1 couvre exactement 2024');

        // Référence : un véhicule mono-VFC sans pivot doit produire
        // exactement la même taxe (puisque v2 n'agit qu'en 2025).
        $vehicleMono = $this->makeVehicleWithSingleVfc(co2: 100);
        $contractsMono = [$this->syntheticContract($vehicleMono, '2024-01-01', '2024-12-31', ContractType::Lld)];
        $mono = $this->executor->execute($this->buildContext($vehicleMono, $contractsMono));

        $this->assertSame($mono->daysAssigned, $result->daysAssigned);
        $this->assertEqualsWithDelta($mono->co2Due, $result->co2Due, 0.01,
            'le pivot 31/12 → 01/01 ne doit pas modifier la taxe 2024');
    }

    #[Test]
    public function lcd_traversant_2_vfc_avec_indispo_reductrice_dans_segment_2_n_a_pas_de_double_comptage(): void
    {
        // Setup : VFC change le 16/06/2024
        // Contrat LCD 28 jours du 01/06 → 28/06 (15 j en v1 + 13 j en v2)
        //   → durée totale 28 j ≤ 30 j → exonération LCD R-2024-021.
        // Indispo réductrice de 5 j en septembre (ne touche pas le contrat).
        // Comme le contrat est exonéré LCD, totalDue doit être 0.
        // L'indispo en septembre n'a aucun effet (pas de jour-contrat à
        // soustraire dans son segment).
        $vehicle = $this->makeVehicleWithTwoDifferentVfcs(
            v1Co2: 100,
            v2Co2: 100,
            switchDate: '2024-06-16',
        );
        $contracts = [$this->syntheticContract($vehicle, '2024-06-01', '2024-06-28', ContractType::Lcd)];
        $vehicleEvents = [$this->syntheticVehicleEvent(
            $vehicle,
            '2024-09-01',
            '2024-09-05',
            title: 'Mise en fourrière',
            hasFiscalImpact: true,
        )];

        $context = new PipelineContext(
            vehicle: $vehicle,
            fiscalYear: 2024,
            daysInYear: $this->yearContext->daysInYear(2024),
            contractsForPair: $contracts,
            vehicleUnavailabilitiesInYear: $vehicleEvents,
        );
        $result = $this->executor->execute($context);

        $this->assertTrue($result->lcdExempt,
            'un contrat 28 j ≤ 30 traversant un changement de VFC reste qualifié LCD');
        $this->assertSame(0.0, $result->totalDue,
            'l\'exonération LCD doit annuler les deux taxes même en multi-VFC');
        $this->assertSame(0, $result->daysAssigned,
            'aucun jour taxable, l\'indispo en sept. ne crée pas de jour fantôme');
    }

    #[Test]
    public function multi_vfc_avec_pivot_regle_partielle_et_lcd_chevauchant_les_2_pivots_n_a_pas_de_double_decompte(): void
    {
        // Sanity audit κ-bis (concern test 7) : valide la chaîne triple
        // VFC × Règles × LCD. Cas extrême combinant les 3 axes :
        //
        //   - 2 VFC : pivot 16/06/2024 (v1 100 g WLTP, v2 150 g WLTP)
        //   - 2 segments règles : pivot 01/07/2024 (stub partial pricing
        //     ajouté par-dessus les 16 règles 2024 existantes)
        //   - 1 contrat LCD 25 j du 16/06 au 10/07/2024 (chevauche les
        //     2 pivots et est ≤ 30 jours → qualifié LCD)
        //
        // Cartesian non-vide attendu : 3 partials
        //   (1) VFC1 × Rule1 (Jan-Jun30) → vide (VFC1 finit le 15/06, OK)
        //       Wait → VFC1 = Jan→15/06, Rule1 = Jan→Jun30 : intersection
        //       Jan→15/06. Mais aucun contrat dans cette plage → 0 jour.
        //   (2) VFC2 × Rule1 (16/06→Jun30) → 15 jours dans la plage
        //   (3) VFC2 × Rule2 (Jul→Dec) → 184 jours, contrat = 10 jours
        //       (01-10 juillet) dans cette plage
        //
        // Tous les partials voient le même contrat LCD entier (R-2024-021
        // n'utilise pas DaysWindow pour le verdict). Le verdict = 25
        // jours exempts. R-2024-002 dans chaque partial filtre par
        // DaysWindow et applique max(0, totalDays - 25). Avec un contrat
        // LCD, chaque partial finit à daysAssigned = 0.
        //
        // Sanity attendue : daysAssigned total = 0, lcdExempt = true,
        // totalDue = 0.

        $registry = $this->app->make(FiscalRuleRegistry::class);
        $boot = new Year2024Boot;
        $registry->register(2024, [
            ...$boot->rules(),
            MultiVfcMultiRuleStubPricing::class,
        ]);
        $this->app->forgetInstance(RuleEffectiveSegmenter::class);
        $this->executor = $this->app->make(FiscalSegmentedExecutor::class);

        $vehicle = $this->makeVehicleWithTwoDifferentVfcs(
            v1Co2: 100,
            v2Co2: 150,
            switchDate: '2024-06-16',
        );
        $contracts = [$this->syntheticContract($vehicle, '2024-06-16', '2024-07-10', ContractType::Lcd)];

        $context = new PipelineContext(
            vehicle: $vehicle,
            fiscalYear: 2024,
            daysInYear: $this->yearContext->daysInYear(2024),
            contractsForPair: $contracts,
            vehicleUnavailabilitiesInYear: [],
        );

        $result = $this->executor->execute($context);

        $this->assertTrue($result->lcdExempt,
            '25 jours ≤ 30 → contrat LCD, doit le rester en multi-VFC × multi-règles');
        $this->assertSame(0, $result->daysAssigned,
            'tous les jours du contrat LCD doivent être exonérés sur les 3 axes');
        $this->assertSame(0.0, $result->totalDue,
            'l\'exonération LCD annule la taxe sur la chaîne combinée');
    }

    // --- Helpers --------------------------------------------------------

    /**
     * @param  list<Contract>  $contracts
     */
    private function buildContext(Vehicle $vehicle, array $contracts): PipelineContext
    {
        return new PipelineContext(
            vehicle: $vehicle,
            fiscalYear: 2024,
            daysInYear: $this->yearContext->daysInYear(2024),
            contractsForPair: $contracts,
            vehicleUnavailabilitiesInYear: [],
        );
    }

    private function makeVehicleWithSingleVfc(int $co2): Vehicle
    {
        $vehicle = $this->createBareVehicle();
        VehicleFiscalCharacteristics::create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => Carbon::parse('2024-01-01'),
            'effective_to' => null,
            ...$this->vfcCommonFields(co2: $co2),
        ]);

        return $vehicle->fresh();
    }

    private function makeVehicleWithTwoDifferentVfcs(int $v1Co2, int $v2Co2, string $switchDate): Vehicle
    {
        $vehicle = $this->createBareVehicle();
        $switch = Carbon::parse($switchDate);
        VehicleFiscalCharacteristics::create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => Carbon::parse('2024-01-01'),
            'effective_to' => $switch->copy()->subDay(),
            ...$this->vfcCommonFields(co2: $v1Co2),
        ]);
        VehicleFiscalCharacteristics::create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => $switch,
            'effective_to' => null,
            ...$this->vfcCommonFields(co2: $v2Co2),
        ]);

        return $vehicle->fresh();
    }

    private function makeVehicleWithThreeDifferentVfcs(
        int $v1Co2,
        string $v1End,
        int $v2Co2,
        string $v2End,
        int $v3Co2,
    ): Vehicle {
        $vehicle = $this->createBareVehicle();

        $v1EndDate = Carbon::parse($v1End);
        $v2StartDate = $v1EndDate->copy()->addDay();
        $v2EndDate = Carbon::parse($v2End);
        $v3StartDate = $v2EndDate->copy()->addDay();

        VehicleFiscalCharacteristics::create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => Carbon::parse('2024-01-01'),
            'effective_to' => $v1EndDate,
            ...$this->vfcCommonFields(co2: $v1Co2),
        ]);
        VehicleFiscalCharacteristics::create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => $v2StartDate,
            'effective_to' => $v2EndDate,
            ...$this->vfcCommonFields(co2: $v2Co2),
        ]);
        VehicleFiscalCharacteristics::create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => $v3StartDate,
            'effective_to' => null,
            ...$this->vfcCommonFields(co2: $v3Co2),
        ]);

        return $vehicle->fresh();
    }

    private function makeVehicleWithFourVfcsHandicapMiddle(): Vehicle
    {
        $vehicle = $this->createBareVehicle();
        $boundaries = [
            ['2024-01-01', '2024-03-31', false],
            ['2024-04-01', '2024-06-30', true],
            ['2024-07-01', '2024-09-30', true],
            ['2024-10-01', null, false],
        ];
        foreach ($boundaries as [$from, $to, $handicap]) {
            VehicleFiscalCharacteristics::create([
                'vehicle_id' => $vehicle->id,
                'effective_from' => Carbon::parse($from),
                'effective_to' => $to === null ? null : Carbon::parse($to),
                ...$this->vfcCommonFields(co2: 100, handicap: $handicap),
            ]);
        }

        return $vehicle->fresh();
    }

    private function makeVehicleWithPivotVfcAtYearEnd(): Vehicle
    {
        $vehicle = $this->createBareVehicle();
        VehicleFiscalCharacteristics::create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => Carbon::parse('2024-01-01'),
            'effective_to' => Carbon::parse('2024-12-31'),
            ...$this->vfcCommonFields(co2: 100),
        ]);
        VehicleFiscalCharacteristics::create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => Carbon::parse('2025-01-01'),
            'effective_to' => null,
            ...$this->vfcCommonFields(co2: 175),
        ]);

        return $vehicle->fresh();
    }

    private function createBareVehicle(): Vehicle
    {
        return Vehicle::create([
            'license_plate' => $this->nextPlate(),
            'brand' => 'Renault',
            'model' => 'Test',
            'first_french_registration_date' => Carbon::parse('2022-01-01'),
            'first_origin_registration_date' => Carbon::parse('2022-01-01'),
            'first_economic_use_date' => Carbon::parse('2022-01-01'),
            'acquisition_date' => Carbon::parse('2022-01-01'),
            'current_status' => VehicleStatus::Active,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function vfcCommonFields(int $co2, bool $handicap = false): array
    {
        return [
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
            'handicap_access' => $handicap,
            'change_reason' => FiscalCharacteristicsChangeReason::InitialCreation,
        ];
    }

    private function syntheticContract(Vehicle $vehicle, string $start, string $end, ContractType $type): Contract
    {
        $contract = new Contract;
        $contract->setRawAttributes([
            'vehicle_id' => $vehicle->id,
            'company_id' => 0,
            'start_date' => $start,
            'end_date' => $end,
            'contract_reference' => null,
            'contract_type' => $type->value,
            'notes' => null,
        ], true);

        return $contract;
    }

    private function syntheticVehicleEvent(
        Vehicle $vehicle,
        string $start,
        string $end,
        string $title,
        bool $hasFiscalImpact,
    ): VehicleEvent {
        $vehicleEvent = new VehicleEvent;
        $vehicleEvent->setRawAttributes([
            'vehicle_id' => $vehicle->id,
            'start_date' => $start,
            'end_date' => $end,
            'title' => $title,
            'has_fiscal_impact' => $hasFiscalImpact,
            'note' => null,
        ], true);

        return $vehicleEvent;
    }

    private static int $plateCounter = 0;

    private function nextPlate(): string
    {
        $n = ++self::$plateCounter;

        return sprintf('MVE-%03d-MVE', $n);
    }
}

/**
 * Stub de règle partielle 2024 (utilisée uniquement par le test
 * « multi_vfc_avec_pivot_regle_partielle_et_lcd... »). Ajoute un
 * pivot règle au 01/07/2024 pour tester le cartésien VFC × Règles
 * combiné à la qualification LCD.
 *
 * **Hors scope production** : registrée à la volée dans le test, jamais
 * dans le boot officiel.
 */
final readonly class MultiVfcMultiRuleStubPricing implements PricingRule
{
    use RuleActiveByDefaultTrait;

    public function ruleCode(): string
    {
        return 'R-2024-MVEx-PARTIAL';
    }

    public function name(): string
    {
        return '[STUB κ-bis test] Tarif partiel 01/07/2024 → 31/12/2024';
    }

    public function description(): string
    {
        return 'Stub utilisé pour tester la chaîne VFC × Règles × LCD combinée.';
    }

    public function ruleType(): RuleType
    {
        return RuleType::Tariff;
    }

    public function displayOrder(): int
    {
        return 999;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legalBasis(): array
    {
        return [];
    }

    /**
     * @return list<TaxType>
     */
    public function taxesConcerned(): array
    {
        return [TaxType::Co2];
    }

    public function applicabilityStart(): CarbonImmutable
    {
        return CarbonImmutable::create(2024, 7, 1);
    }

    public function applicabilityEnd(): ?CarbonImmutable
    {
        return CarbonImmutable::create(2024, 12, 31);
    }

    public function price(PipelineContext $context): PipelineContext
    {
        // No-op : ne touche pas au tarif. Seul intérêt : créer un pivot
        // règle qui force le segmenteur à produire 2 segments en 2024.
        return $context->withAppliedRule($this->ruleCode());
    }

    public function pedagogicalContent(): RulePedagogicalContent
    {
        return new RulePedagogicalContent(
            tab: RuleTab::Calcul,
            section: RuleSection::Bareme,
            title: 'Test stub',
            pitch: 'Stub utilisé en tests · non rendu en UI.',
        );
    }
}
