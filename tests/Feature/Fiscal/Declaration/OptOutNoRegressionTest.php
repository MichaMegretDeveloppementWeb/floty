<?php

declare(strict_types=1);

namespace Tests\Feature\Fiscal\Declaration;

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
use App\Enums\VehicleEvent\VehicleEventType;
use App\Fiscal\Pipeline\FiscalPipeline;
use App\Fiscal\Pipeline\FiscalSegmentedExecutor;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\Pipeline\RuleEffectiveSegmenter;
use App\Fiscal\Registry\FiscalRuleRegistry;
use App\Fiscal\Registry\OverlayedRuleRegistry;
use App\Fiscal\Year2024\Exemption\R2024_021_ShortTermRental;
use App\Fiscal\Year2024\Exemption\R2024_021_WithOptOuts;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Vehicle;
use App\Models\VehicleEvent;
use App\Models\VehicleFiscalCharacteristics;
use App\Repositories\User\Vehicle\VehicleFiscalCharacteristicsReadRepository;
use App\Services\Shared\Fiscal\FiscalYearContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * No-regression invariant Phase 11 D5.1 :
 *   - SANS opt-outs, un calcul via `OverlayedRuleRegistry` produit
 *     **strictement le même résultat** qu'un calcul via le registry
 *     standard. Garantie de l'invariant κ + base de confiance pour
 *     l'orchestration D5.2.
 *   - AVEC opt-outs sur tous les LCD du couple, le calcul produit un
 *     montant **strictement supérieur** (les LCD deviennent taxables).
 */
final class OptOutNoRegressionTest extends TestCase
{
    use RefreshDatabase;

    private FiscalSegmentedExecutor $standardExecutor;

    private FiscalRuleRegistry $baseRegistry;

    private FiscalYearContext $yearContext;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->standardExecutor = $this->app->make(FiscalSegmentedExecutor::class);
        $this->baseRegistry = $this->app->make(FiscalRuleRegistry::class);
        $this->yearContext = $this->app->make(FiscalYearContext::class);
        $this->company = Company::factory()->create();
    }

    #[Test]
    public function calcul_avec_overlayed_registry_sans_opt_outs_donne_strictement_le_meme_resultat_que_le_standard(): void
    {
        $vehicle = $this->makeVehicleWithSingleVfc();
        $contracts = [
            $this->lcdContract($vehicle, '2024-04-01', '2024-04-15'),  // 15 j LCD
            $this->lldContract($vehicle, '2024-05-01', '2024-12-31'),  // 245 j LLD
        ];
        $context = $this->buildContext($vehicle, $contracts);

        // Calcul standard (registry de base, R-2024-021 brut).
        $standard = $this->standardExecutor->execute($context);

        // Calcul via OverlayedRegistry SANS opt-outs (decorator wraps mais
        // ne filtre rien). Doit produire le même résultat exact.
        $overlayedExecutor = $this->makeOverlayedExecutor([]);
        $overlayed = $overlayedExecutor->execute($context);

        self::assertSame($standard->daysAssigned, $overlayed->daysAssigned);
        self::assertSame($standard->co2DueRaw, $overlayed->co2DueRaw);
        self::assertSame($standard->pollutantsDueRaw, $overlayed->pollutantsDueRaw);
        self::assertSame($standard->totalDue, $overlayed->totalDue);
        self::assertSame($standard->lcdExempt, $overlayed->lcdExempt);
    }

    #[Test]
    public function avec_opt_outs_sur_le_contrat_lcd_la_taxe_devient_strictement_superieure_au_standard(): void
    {
        $vehicle = $this->makeVehicleWithSingleVfc();
        $lcdContract = $this->lcdContract($vehicle, '2024-04-01', '2024-04-15'); // 15 j LCD
        $lldContract = $this->lldContract($vehicle, '2024-05-01', '2024-12-31'); // 245 j LLD
        $contracts = [$lcdContract, $lldContract];
        $context = $this->buildContext($vehicle, $contracts);

        // Calcul standard : LCD est exonéré (15 j retirés du numérateur).
        $standard = $this->standardExecutor->execute($context);

        // Calcul avec opt-out sur le contrat LCD : il devient taxable.
        $overlayedExecutor = $this->makeOverlayedExecutor([$lcdContract->id]);
        $withOptOut = $overlayedExecutor->execute($context);

        self::assertGreaterThan(
            $standard->daysAssigned,
            $withOptOut->daysAssigned,
            'le contrat LCD requalifié ajoute ses 15 jours au numérateur',
        );
        self::assertGreaterThan(
            $standard->co2Due,
            $withOptOut->co2Due,
            'la taxe CO₂ doit augmenter quand le LCD perd son exonération',
        );
        self::assertSame(
            $standard->daysAssigned + 15,
            $withOptOut->daysAssigned,
            'exactement 15 jours supplémentaires (durée du LCD opt-out)',
        );
    }

    #[Test]
    public function r2024_008_indispos_reductrice_combinees_avec_opt_out_r2024_021(): void
    {
        // T6 audit pré-livraison : un LCD de 30j + 10j d'indispos
        // réductrices `AccidentNoCirculation` (R-2024-008) qui chevauchent
        // le LCD. Sans opt-out, le LCD est exonéré. Avec opt-out
        // (Requalified), le LCD passe imposable, mais R-2024-008
        // continue de réduire les jours non-circulants.
        //
        // Setup : LCD 30j en juin (2024-06-01 → 2024-06-30) + LLD
        // full-year qui couvre l'année (assiette taxable). Indispo
        // 2024-06-10 → 2024-06-19 (10j) sur ce véhicule.
        //
        // Cas A · Sans opt-out : standard. R-2024-021 exonère le LCD,
        //   R-2024-008 ne s'applique qu'aux indispos hors-LCD (le LCD
        //   est déjà exonéré). Total = LLD année - LCD jours -
        //   indispos hors-LCD.
        //
        // Cas B · Avec opt-out (LCD requalifié) : R-2024-021 retire
        //   l'exonération, le LCD redevient taxable. R-2024-008
        //   continue de réduire les 10j d'indispos. Total = LLD
        //   année - 10j indispos. La taxe doit augmenter par rapport
        //   au cas A (les 30j LCD - 10j indispos = 20j net se rajoutent).
        $vehicle = $this->makeVehicleWithSingleVfc();
        // LLD sur les 5 premiers mois + LCD juin + LLD aoû→déc
        // pour respecter le trigger anti-overlap par véhicule.
        $lldContract1 = $this->lldContract($vehicle, '2024-01-01', '2024-05-31');
        $lcdContract = $this->lcdContract($vehicle, '2024-06-01', '2024-06-30');
        $lldContract2 = $this->lldContract($vehicle, '2024-07-01', '2024-12-31');

        $vehicleEvent = new VehicleEvent;
        $vehicleEvent->setRawAttributes([
            'vehicle_id' => $vehicle->id,
            'start_date' => '2024-06-10',
            'end_date' => '2024-06-19',
            'type' => VehicleEventType::AccidentNoCirculation->value,
            'has_fiscal_impact' => true,
            'description' => null,
        ], true);
        $vehicleEvent->save();

        $contracts = [$lldContract1, $lcdContract, $lldContract2];
        $context = new PipelineContext(
            vehicle: $vehicle,
            fiscalYear: 2024,
            daysInYear: $this->yearContext->daysInYear(2024),
            contractsForPair: $contracts,
            vehicleUnavailabilitiesInYear: [$vehicleEvent],
        );

        // Cas A · standard sans opt-out
        $standardExecutor = $this->makeOverlayedExecutor([]);
        $standard = $standardExecutor->execute($context);

        // Cas B · LCD requalifié (opt-out)
        $requalifiedExecutor = $this->makeOverlayedExecutor([$lcdContract->id]);
        $requalified = $requalifiedExecutor->execute($context);

        // Avec LCD requalifié, le total dû doit être strictement
        // supérieur (les 30j LCD réintégrés moins les 10j d'indispos
        // = 20j taxables supplémentaires).
        self::assertGreaterThan(
            $standard->totalDue,
            $requalified->totalDue,
            'Requalifier le LCD doit augmenter la taxe.',
        );

        // Le delta exact devrait correspondre à 20j (30j LCD - 10j
        // indispos R-2024-008). On valide la cohérence ordre de
        // grandeur sans figer un montant exact (dépend du barème CO₂
        // 2024 et des prorata précis).
        $deltaDays = $requalified->daysAssigned - $standard->daysAssigned;
        self::assertGreaterThanOrEqual(
            15,
            $deltaDays,
            'Delta jours doit refléter ~20j (30j LCD - 10j indispos).',
        );
        self::assertLessThanOrEqual(
            25,
            $deltaDays,
            'Delta jours ne doit pas dépasser la durée brute du LCD.',
        );
    }

    #[Test]
    public function opt_out_sur_un_contrat_non_lcd_n_a_aucun_effet(): void
    {
        $vehicle = $this->makeVehicleWithSingleVfc();
        $lldContract = $this->lldContract($vehicle, '2024-01-01', '2024-12-31');
        $contracts = [$lldContract];
        $context = $this->buildContext($vehicle, $contracts);

        $standard = $this->standardExecutor->execute($context);

        // Opt-out sur un contrat qui n'était PAS LCD : aucun effet attendu.
        $overlayedExecutor = $this->makeOverlayedExecutor([$lldContract->id]);
        $overlayed = $overlayedExecutor->execute($context);

        self::assertSame($standard->daysAssigned, $overlayed->daysAssigned);
        self::assertSame($standard->totalDue, $overlayed->totalDue);
    }

    /**
     * @param  list<int>  $optOutContractIds
     */
    private function makeOverlayedExecutor(array $optOutContractIds): FiscalSegmentedExecutor
    {
        $decorator = new R2024_021_WithOptOuts(
            new R2024_021_ShortTermRental,
            $optOutContractIds,
        );
        $overlayedRegistry = new OverlayedRuleRegistry(
            $this->app,
            $this->baseRegistry,
            $decorator,
            2024,
        );
        $segmenter = new RuleEffectiveSegmenter($overlayedRegistry);

        return new FiscalSegmentedExecutor(
            $this->app->make(VehicleFiscalCharacteristicsReadRepository::class),
            $segmenter,
            $this->app->make(FiscalPipeline::class),
        );
    }

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

    private function makeVehicleWithSingleVfc(): Vehicle
    {
        $vehicle = Vehicle::create([
            'license_plate' => sprintf('D5-%03d-D5', random_int(1, 999)),
            'brand' => 'Peugeot',
            'model' => '308',
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

    private function lcdContract(Vehicle $vehicle, string $start, string $end): Contract
    {
        $contract = new Contract;
        $contract->setRawAttributes([
            'vehicle_id' => $vehicle->id,
            'company_id' => $this->company->id,
            'start_date' => $start,
            'end_date' => $end,
            'contract_reference' => null,
            'contract_type' => ContractType::Lcd->value,
            'notes' => null,
        ], true);
        $contract->save();

        return $contract;
    }

    private function lldContract(Vehicle $vehicle, string $start, string $end): Contract
    {
        $contract = new Contract;
        $contract->setRawAttributes([
            'vehicle_id' => $vehicle->id,
            'company_id' => $this->company->id,
            'start_date' => $start,
            'end_date' => $end,
            'contract_reference' => null,
            'contract_type' => ContractType::Lld->value,
            'notes' => null,
        ], true);
        $contract->save();

        return $contract;
    }
}
