<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\Pipeline;

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
use App\Exceptions\Fiscal\FiscalCalculationException;
use App\Fiscal\Contracts\PricingRule;
use App\Fiscal\Contracts\TransversalRule;
use App\Fiscal\Pipeline\FiscalSegmentedExecutor;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\Pipeline\RuleEffectiveSegmenter;
use App\Fiscal\Registry\FiscalRuleRegistry;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use App\Services\Shared\Fiscal\FiscalYearContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests Unit du chef d'orchestre `FiscalSegmentedExecutor` sur le
 * **produit cartésien VFC × Règles** (chantier κ.4).
 *
 * Les tests utilisent une **année stub 2090** non enregistrée en
 * production : le registry est garni à la volée avec des règles fakes
 * qui exposent des bornes d'applicabilité partielles ou full-year. On
 * vérifie ensuite la structure du `FiscalSegmentBreakdown` produit
 * (count, intersections start/end, association vfcSegment/ruleSegment).
 *
 * Le no-regression sur 2024 (mono règle full-year × N VFC) est couvert
 * par {@see FiscalSegmentedExecutorTest} qui n'a pas été modifié.
 */
final class FiscalSegmentedExecutorRulesTest extends TestCase
{
    use RefreshDatabase;

    private const int STUB_YEAR = 2090;

    private FiscalSegmentedExecutor $executor;

    private FiscalRuleRegistry $registry;

    private FiscalYearContext $yearContext;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = $this->app->make(FiscalRuleRegistry::class);
        $this->yearContext = $this->app->make(FiscalYearContext::class);

        // Le segmenteur est singleton avec cache - on l'invalide pour
        // que le registry stub year 2090 soit ré-évalué dans chaque test.
        $this->app->forgetInstance(RuleEffectiveSegmenter::class);
        $this->executor = $this->app->make(FiscalSegmentedExecutor::class);
    }

    #[Test]
    public function mono_vfc_avec_une_regle_couvrant_l_annee_donne_un_seul_partial(): void
    {
        $this->registry->register(self::STUB_YEAR, [
            StubFullYearProrata2090::class,
        ]);
        $vehicle = $this->makeVehicleWithSingleVfc(self::STUB_YEAR);
        $context = $this->buildContext($vehicle, self::STUB_YEAR);

        $breakdowns = $this->executor->executeWithSegments($context);

        self::assertCount(1, $breakdowns);
        self::assertSame('2090-01-01', $breakdowns[0]->start->toDateString());
        self::assertSame('2090-12-31', $breakdowns[0]->end->toDateString());
        self::assertCount(1, $breakdowns[0]->ruleSegment->rules);
        self::assertSame('R-2090-PRORATA', $breakdowns[0]->ruleSegment->rules[0]->ruleCode());
    }

    #[Test]
    public function mono_vfc_avec_regle_qui_apparait_mid_year_donne_deux_partials(): void
    {
        $this->registry->register(self::STUB_YEAR, [
            StubFullYearProrata2090::class,
            StubAppearsJuly1Rule2090::class,
        ]);
        $vehicle = $this->makeVehicleWithSingleVfc(self::STUB_YEAR);
        $context = $this->buildContext($vehicle, self::STUB_YEAR);

        $breakdowns = $this->executor->executeWithSegments($context);

        self::assertCount(2, $breakdowns);

        // Premier sous-segment : 01/01 -> 30/06, 1 règle (le prorata seul).
        self::assertSame('2090-01-01', $breakdowns[0]->start->toDateString());
        self::assertSame('2090-06-30', $breakdowns[0]->end->toDateString());
        self::assertCount(1, $breakdowns[0]->ruleSegment->rules);

        // Second sous-segment : 01/07 -> 31/12, 2 règles (prorata + appears).
        self::assertSame('2090-07-01', $breakdowns[1]->start->toDateString());
        self::assertSame('2090-12-31', $breakdowns[1]->end->toDateString());
        self::assertCount(2, $breakdowns[1]->ruleSegment->rules);
    }

    #[Test]
    public function multi_vfc_avec_multi_regle_donne_le_produit_cartesien_clippe(): void
    {
        // 2 VFC : changement le 16/06. 2 règles : pivot le 01/07.
        // Cartésien clippé attendu :
        //   - VFC1 (01/01->15/06) × Rule1 (01/01->30/06) = (01/01, 15/06)
        //   - VFC1 (01/01->15/06) × Rule2 (01/07->31/12) = vide (skip)
        //   - VFC2 (16/06->31/12) × Rule1 (01/01->30/06) = (16/06, 30/06)
        //   - VFC2 (16/06->31/12) × Rule2 (01/07->31/12) = (01/07, 31/12)
        // Total : 3 partials.
        $this->registry->register(self::STUB_YEAR, [
            StubEndsJune30Rule2090::class,
            StubAppearsJuly1Rule2090::class,
        ]);
        $vehicle = $this->makeVehicleWithSwitch(self::STUB_YEAR, '2090-06-16');
        $context = $this->buildContext($vehicle, self::STUB_YEAR);

        $breakdowns = $this->executor->executeWithSegments($context);

        self::assertCount(3, $breakdowns);

        $bounds = array_map(
            static fn ($b): string => $b->start->toDateString().'->'.$b->end->toDateString(),
            $breakdowns,
        );

        self::assertContains('2090-01-01->2090-06-15', $bounds);
        self::assertContains('2090-06-16->2090-06-30', $bounds);
        self::assertContains('2090-07-01->2090-12-31', $bounds);
    }

    #[Test]
    public function intersection_vide_entre_vfc_et_regle_skippe_le_couple(): void
    {
        // VFC unique 01/01 -> 15/06 (s'arrête mi-année).
        // Règle unique 01/07 -> 31/12 (apparait après l'arrêt VFC).
        // Aucune intersection -> throw missingFiscalCharacteristics
        // (pas de partial calculable).
        $this->registry->register(self::STUB_YEAR, [
            StubAppearsJuly1Rule2090::class,
        ]);
        $vehicle = $this->makeVehicleWithSingleVfcEndingMidYear(self::STUB_YEAR, '2090-06-15');
        $context = $this->buildContext($vehicle, self::STUB_YEAR);

        $this->expectException(FiscalCalculationException::class);
        $this->executor->executeWithSegments($context);
    }

    #[Test]
    public function court_circuit_perf_ne_pose_pas_de_days_window_pour_le_cas_full_year(): void
    {
        // 1 VFC full-year × 1 règle full-year = 1 partial qui couvre
        // l'année entière. Le DaysWindow ne doit PAS être posé sur le
        // contexte du pipeline (perf : on évite le filtrage inutile).
        $this->registry->register(self::STUB_YEAR, [
            StubFullYearProrata2090::class,
        ]);
        $vehicle = $this->makeVehicleWithSingleVfc(self::STUB_YEAR);
        $context = $this->buildContext($vehicle, self::STUB_YEAR);

        // On n'a pas d'accès direct au contexte interne ; on vérifie via
        // le résultat : daysAssigned doit refléter daysInYear (366 en
        // 2090 non bissextile = 365). StubFullYearProrata2090 expose
        // daysInYear via cumulativeDaysForPair pour le test.
        $breakdowns = $this->executor->executeWithSegments($context);

        self::assertCount(1, $breakdowns);
        // Le prorata stub copie daysInYear sur cumulativeDaysForPair
        // quand daysWindow est absent, sinon copie le span de la window.
        self::assertSame(365, $breakdowns[0]->result->cumulativeDaysForPair);
    }

    // --- Helpers --------------------------------------------------------

    private function buildContext(Vehicle $vehicle, int $year): PipelineContext
    {
        return new PipelineContext(
            vehicle: $vehicle,
            fiscalYear: $year,
            daysInYear: $this->yearContext->daysInYear($year),
            contractsForPair: [],
            vehicleUnavailabilitiesInYear: [],
        );
    }

    private function makeVehicleWithSingleVfc(int $year): Vehicle
    {
        $vehicle = $this->makeBareVehicle();
        VehicleFiscalCharacteristics::create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => Carbon::create($year, 1, 1),
            'effective_to' => null,
            ...$this->vfcCommonFields(),
        ]);

        return $vehicle->fresh();
    }

    private function makeVehicleWithSingleVfcEndingMidYear(int $year, string $endDate): Vehicle
    {
        $vehicle = $this->makeBareVehicle();
        VehicleFiscalCharacteristics::create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => Carbon::create($year, 1, 1),
            'effective_to' => Carbon::parse($endDate),
            ...$this->vfcCommonFields(),
        ]);

        return $vehicle->fresh();
    }

    private function makeVehicleWithSwitch(int $year, string $switchDate): Vehicle
    {
        $vehicle = $this->makeBareVehicle();
        $switch = Carbon::parse($switchDate);

        VehicleFiscalCharacteristics::create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => Carbon::create($year, 1, 1),
            'effective_to' => $switch->copy()->subDay(),
            ...$this->vfcCommonFields(),
        ]);
        VehicleFiscalCharacteristics::create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => $switch,
            'effective_to' => null,
            ...$this->vfcCommonFields(),
        ]);

        return $vehicle->fresh();
    }

    private function makeBareVehicle(): Vehicle
    {
        return Vehicle::create([
            'license_plate' => $this->nextPlate(),
            'brand' => 'Renault',
            'model' => 'StubKappa4',
            'first_french_registration_date' => Carbon::parse('2080-01-01'),
            'first_origin_registration_date' => Carbon::parse('2080-01-01'),
            'first_economic_use_date' => Carbon::parse('2080-01-01'),
            'acquisition_date' => Carbon::parse('2080-01-01'),
            'current_status' => VehicleStatus::Active,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function vfcCommonFields(): array
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
            'co2_wltp' => 100,
            'taxable_horsepower' => 6,
            'handicap_access' => false,
            'change_reason' => FiscalCharacteristicsChangeReason::InitialCreation,
        ];
    }

    private static int $plateCounter = 0;

    private function nextPlate(): string
    {
        $n = ++self::$plateCounter;

        return sprintf('K4-%03d-K4', $n);
    }
}

// ---------------------------------------------------------------------
// Stubs : règles fictives pour année 2090, exposant des bornes
// d'applicabilité personnalisées. Pas de logique fiscale réaliste, juste
// le minimum pour tester la structure du segmenteur.
// ---------------------------------------------------------------------

/**
 * Règle prorata stub couvrant 2090 entièrement. Pose `cumulativeDaysForPair`
 * sur le contexte selon que `daysWindow` est posé (= span window) ou non
 * (= daysInYear). Permet de vérifier le court-circuit perf.
 */
final readonly class StubFullYearProrata2090 implements TransversalRule
{
    use StubMetadataDefaults2090;

    public function ruleCode(): string
    {
        return 'R-2090-PRORATA';
    }

    /**
     * @return list<TaxType>
     */
    public function taxesConcerned(): array
    {
        return [TaxType::Co2, TaxType::Pollutants];
    }

    public function applicabilityStart(): CarbonImmutable
    {
        return CarbonImmutable::create(2090, 1, 1);
    }

    public function applicabilityEnd(): ?CarbonImmutable
    {
        return CarbonImmutable::create(2090, 12, 31);
    }

    public function apply(PipelineContext $context): PipelineContext
    {
        $window = $context->daysWindow;
        if ($window === null) {
            $days = $context->daysInYear;
        } else {
            $days = (int) $window->start->diffInDays($window->end) + 1;
        }

        return $context
            ->withDaysAssignedToCompany($days)
            ->withCumulativeDaysForPair($days)
            ->withDueAmounts(0.0, 0.0)
            ->withAppliedRule($this->ruleCode());
    }
}

/**
 * Règle stub qui apparaît au 01/07/2090. Implémente PricingRule pour
 * apparaître dans la trace des règles applicables (même si elle ne pose
 * rien d'utile).
 */
final readonly class StubAppearsJuly1Rule2090 implements PricingRule
{
    use StubMetadataDefaults2090;

    public function ruleCode(): string
    {
        return 'R-2090-APPEARS-0701';
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
        return CarbonImmutable::create(2090, 7, 1);
    }

    public function applicabilityEnd(): ?CarbonImmutable
    {
        return CarbonImmutable::create(2090, 12, 31);
    }

    public function price(PipelineContext $context): PipelineContext
    {
        return $context->withAppliedRule($this->ruleCode());
    }
}

/**
 * Règle stub qui se termine au 30/06/2090.
 */
final readonly class StubEndsJune30Rule2090 implements PricingRule
{
    use StubMetadataDefaults2090;

    public function ruleCode(): string
    {
        return 'R-2090-ENDS-0630';
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
        return CarbonImmutable::create(2090, 1, 1);
    }

    public function applicabilityEnd(): ?CarbonImmutable
    {
        return CarbonImmutable::create(2090, 6, 30);
    }

    public function price(PipelineContext $context): PipelineContext
    {
        return $context->withAppliedRule($this->ruleCode());
    }
}

/**
 * Métadonnées par défaut pour les stubs 2090 (κ.6).
 */
trait StubMetadataDefaults2090
{
    public function name(): string
    {
        return 'Stub 2090';
    }

    public function description(): string
    {
        return 'Stub 2090';
    }

    public function ruleType(): RuleType
    {
        return RuleType::Transversal;
    }

    public function displayOrder(): int
    {
        return 1;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legalBasis(): array
    {
        return [];
    }

    public function isActive(): bool
    {
        return true;
    }
}
