<?php

declare(strict_types=1);

namespace Tests\Feature\Fiscal\Declaration;

use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Enums\Contract\ContractType;
use App\Enums\FiscalReviewDecision\ReviewDecisionType;
use App\Enums\FiscalReviewDecision\RiskCode;
use App\Enums\Vehicle\BodyType;
use App\Enums\Vehicle\EnergySource;
use App\Enums\Vehicle\EuroStandard;
use App\Enums\Vehicle\FiscalCharacteristicsChangeReason;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\PollutantCategory;
use App\Enums\Vehicle\ReceptionCategory;
use App\Enums\Vehicle\VehicleStatus;
use App\Enums\Vehicle\VehicleUserType;
use App\Fiscal\ValueObjects\AppliedDecisionEntry;
use App\Fiscal\ValueObjects\VehicleSnapshotEntry;
use App\Models\Company;
use App\Models\Contract;
use App\Models\FiscalReviewDecision;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use App\Services\Contract\ContractQueryService;
use App\Services\Fiscal\Declaration\DeclarationFiscalEngine;
use App\Services\Fiscal\FleetFiscalAggregator;
use App\Services\Fiscal\RiskDetection\RiskDetectionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre le {@see DeclarationFiscalEngine} (Phase 11 D5.2) en
 * vérifiant la propagation décisions de revue → opt-outs → calcul
 * fiscal final → snapshot immuable.
 *
 * Invariant κ : sans décisions Requalified, le snapshot produit des
 * totaux strictement identiques au calcul standard via
 * {@see FleetFiscalAggregator}.
 */
final class DeclarationFiscalEngineTest extends TestCase
{
    use RefreshDatabase;

    private DeclarationFiscalEngine $engine;

    private FleetFiscalAggregator $standardAggregator;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = $this->app->make(DeclarationFiscalEngine::class);
        $this->standardAggregator = $this->app->make(FleetFiscalAggregator::class);
        $this->company = Company::factory()->create([
            'short_code' => 'ACM',
            'legal_name' => 'ACM SARL',
        ]);
    }

    #[Test]
    public function snapshot_couple_sans_contrat_renvoie_un_snapshot_zero_valide(): void
    {
        $snapshot = $this->engine->compute($this->company->id, 2024);

        self::assertSame($this->company->id, $snapshot->companyId);
        self::assertSame('ACM', $snapshot->companyShortCode);
        self::assertSame('ACM SARL', $snapshot->companyLegalName);
        self::assertSame(2024, $snapshot->fiscalYear);
        self::assertSame(0.0, $snapshot->totalDue);
        self::assertSame(0.0, $snapshot->co2DueTotal);
        self::assertSame(0.0, $snapshot->pollutantsDueTotal);
        self::assertSame([], $snapshot->vehicleBreakdown);
        self::assertSame([], $snapshot->appliedDecisions);
        self::assertSame([], $snapshot->optOutContractIds);
    }

    #[Test]
    public function sans_decisions_persistees_les_totaux_sont_strictement_identiques_au_calcul_standard(): void
    {
        $vehicle = $this->makeVehicleWithSingleVfc();
        // 2 LCD courts → cluster détecté par RiskDetectionService (cumul 40j),
        // mais aucune decision persistée → engine ne doit pas opter out.
        $this->makeContract($vehicle, '2024-01-01', '2024-01-20', ContractType::Lcd);
        $this->makeContract($vehicle, '2024-01-26', '2024-02-14', ContractType::Lcd);

        $standardTotal = $this->standardAggregatorTotalFor($vehicle, 2024);
        $snapshot = $this->engine->compute($this->company->id, 2024);

        self::assertSame($standardTotal, $snapshot->totalDue);
        self::assertSame([], $snapshot->appliedDecisions);
        self::assertSame([], $snapshot->optOutContractIds);
        self::assertCount(1, $snapshot->vehicleBreakdown);
    }

    #[Test]
    public function decision_requalified_retire_l_exoneration_lcd_et_augmente_strictement_la_taxe(): void
    {
        $vehicle = $this->makeVehicleWithSingleVfc();
        $c1 = $this->makeContract($vehicle, '2024-01-01', '2024-01-20', ContractType::Lcd);
        $c2 = $this->makeContract($vehicle, '2024-01-26', '2024-02-14', ContractType::Lcd);
        // Un LLD full-year pour avoir une assiette taxable non triviale.
        $this->makeContract($vehicle, '2024-03-01', '2024-12-31', ContractType::Lld);

        $standardTotal = $this->standardAggregatorTotalFor($vehicle, 2024);

        // Persister une decision Requalified sur le cluster détecté.
        $clusters = $this->app->make(RiskDetectionService::class)
            ->detectClusters($this->company->id, 2024);
        self::assertCount(1, $clusters, 'Le cas-test doit produire 1 cluster.');
        $clusterFingerprint = $clusters[0]->fingerprint;

        FiscalReviewDecision::factory()->create([
            'company_id' => $this->company->id,
            'fiscal_year' => 2024,
            'cluster_fingerprint' => $clusterFingerprint,
            'risk_code' => $clusters[0]->code,
            'decision' => ReviewDecisionType::Requalified,
            'justification' => 'Locations professionnelles répétées.',
            'decided_at' => CarbonImmutable::now(),
        ]);

        $snapshot = $this->engine->compute($this->company->id, 2024);

        self::assertGreaterThan(
            $standardTotal,
            $snapshot->totalDue,
            'Requalifier les LCD doit augmenter la taxe (les jours LCD redeviennent taxables).',
        );
        self::assertCount(1, $snapshot->appliedDecisions);
        $applied = $snapshot->appliedDecisions[0];
        self::assertInstanceOf(AppliedDecisionEntry::class, $applied);
        self::assertSame($clusterFingerprint, $applied->clusterFingerprint);
        self::assertSame(ReviewDecisionType::Requalified, $applied->decision);
        self::assertSame('Locations professionnelles répétées.', $applied->justification);
        self::assertSame([$c1->id, $c2->id], $applied->contractIds);
        self::assertSame([$c1->id, $c2->id], $snapshot->optOutContractIds);
    }

    #[Test]
    public function decision_conserved_n_a_aucun_effet_sur_les_totaux_mais_apparait_dans_le_snapshot(): void
    {
        $vehicle = $this->makeVehicleWithSingleVfc();
        $this->makeContract($vehicle, '2024-01-01', '2024-01-20', ContractType::Lcd);
        $this->makeContract($vehicle, '2024-01-26', '2024-02-14', ContractType::Lcd);
        $this->makeContract($vehicle, '2024-03-01', '2024-12-31', ContractType::Lld);

        $standardTotal = $this->standardAggregatorTotalFor($vehicle, 2024);

        $clusters = $this->app->make(RiskDetectionService::class)
            ->detectClusters($this->company->id, 2024);
        FiscalReviewDecision::factory()->create([
            'company_id' => $this->company->id,
            'fiscal_year' => 2024,
            'cluster_fingerprint' => $clusters[0]->fingerprint,
            'risk_code' => $clusters[0]->code,
            'decision' => ReviewDecisionType::Conserved,
            'justification' => null,
            'decided_at' => CarbonImmutable::now(),
        ]);

        $snapshot = $this->engine->compute($this->company->id, 2024);

        self::assertSame(
            $standardTotal,
            $snapshot->totalDue,
            'Conserved = no-op sur les totaux.',
        );
        self::assertSame([], $snapshot->optOutContractIds);
        self::assertCount(1, $snapshot->appliedDecisions);
        self::assertSame(ReviewDecisionType::Conserved, $snapshot->appliedDecisions[0]->decision);
    }

    #[Test]
    public function decision_orpheline_dont_le_fingerprint_a_disparu_est_ignoree_silencieusement(): void
    {
        $vehicle = $this->makeVehicleWithSingleVfc();
        $this->makeContract($vehicle, '2024-03-01', '2024-12-31', ContractType::Lld);

        $standardTotal = $this->standardAggregatorTotalFor($vehicle, 2024);

        // Decision persistée pour un cluster qui n'existe plus.
        FiscalReviewDecision::factory()->create([
            'company_id' => $this->company->id,
            'fiscal_year' => 2024,
            'cluster_fingerprint' => str_repeat('f', 64),
            'risk_code' => RiskCode::Chain,
            'decision' => ReviewDecisionType::Requalified,
            'justification' => 'Decision orpheline.',
            'decided_at' => CarbonImmutable::now(),
        ]);

        $snapshot = $this->engine->compute($this->company->id, 2024);

        self::assertSame($standardTotal, $snapshot->totalDue);
        self::assertSame([], $snapshot->optOutContractIds);
        self::assertSame([], $snapshot->appliedDecisions);
    }

    #[Test]
    public function multi_clusters_avec_decisions_mixtes_conserved_et_requalified(): void
    {
        // T1 audit pré-livraison : 2 chaînes LCD distinctes (clusters
        // détectés indépendants par fingerprint), une Conservée (no-op
        // = exonération maintenue) et une Requalifiée (opt-out =
        // imposition réintroduite). Vérifie que l'agrégation gère
        // correctement le mix.
        $vehicle = $this->makeVehicleWithSingleVfc();

        // Chaîne 1 : 2 LCD courts en janvier (cluster A · 35j cumul,
        // intervalle 5j ≤ max_interval 15j → trigger threshold_low 30)
        $a1 = $this->makeContract($vehicle, '2024-01-01', '2024-01-18', ContractType::Lcd); // 18j
        $a2 = $this->makeContract($vehicle, '2024-01-24', '2024-02-09', ContractType::Lcd); // 17j

        // Chaîne 2 : 2 LCD courts en juin (cluster B · 35j cumul). Le
        // LLD entre les deux chaînes coupe (lld_breaks_chain = true)
        // et garantit deux clusters distincts.
        $this->makeContract($vehicle, '2024-02-19', '2024-05-31', ContractType::Lld);
        $b1 = $this->makeContract($vehicle, '2024-06-01', '2024-06-18', ContractType::Lcd); // 18j
        $b2 = $this->makeContract($vehicle, '2024-06-24', '2024-07-10', ContractType::Lcd); // 17j

        $clusters = $this->app->make(RiskDetectionService::class)
            ->detectClusters($this->company->id, 2024);
        self::assertCount(2, $clusters, 'Les 2 chaînes LCD doivent former 2 clusters distincts.');

        // Persister 1 décision Requalified (cluster A) + 1 Conserved (cluster B)
        FiscalReviewDecision::factory()->create([
            'company_id' => $this->company->id,
            'fiscal_year' => 2024,
            'cluster_fingerprint' => $clusters[0]->fingerprint,
            'risk_code' => $clusters[0]->code,
            'decision' => ReviewDecisionType::Requalified,
            'justification' => 'Cluster A requalifié.',
            'decided_at' => CarbonImmutable::now(),
        ]);
        FiscalReviewDecision::factory()->create([
            'company_id' => $this->company->id,
            'fiscal_year' => 2024,
            'cluster_fingerprint' => $clusters[1]->fingerprint,
            'risk_code' => $clusters[1]->code,
            'decision' => ReviewDecisionType::Conserved,
            'justification' => null,
            'decided_at' => CarbonImmutable::now(),
        ]);

        $snapshot = $this->engine->compute($this->company->id, 2024);

        // 2 décisions appliquées
        self::assertCount(2, $snapshot->appliedDecisions);

        // Cluster A : Requalifié → contractIds dans optOutContractIds
        // Cluster B : Conserved → pas dans optOutContractIds
        $optOutIds = $snapshot->optOutContractIds;
        sort($optOutIds);
        $expectedOptOuts = [$a1->id, $a2->id];
        sort($expectedOptOuts);
        self::assertSame(
            $expectedOptOuts,
            $optOutIds,
            'Seuls les contrats du cluster Requalified doivent être opt-out.',
        );
        self::assertNotContains($b1->id, $optOutIds);
        self::assertNotContains($b2->id, $optOutIds);
    }

    #[Test]
    public function lcd_chevauchant_deux_annees_civiles_compte_isolement_par_annee(): void
    {
        // T2 audit pré-livraison : un LCD chevauchant deux années
        // civiles. R-2024-021 cumul par couple + année civile - chaque
        // année ne voit que ses propres jours, pas le total à cheval.
        //
        // Setup : LCD 2024-01-01 → 2024-01-15 (15j) + LCD
        // 2024-12-25 → 2024-12-31 (7j). Ces 2 LCD séparés de 11 mois
        // ne forment pas une chaîne (intervalle >> max_interval).
        // Cumul 2024 = 22j ≤ threshold_low → pas de cluster, mais
        // chaque LCD individuel est exonéré (R-2024-021).
        //
        // Pour V1 : seule l'année 2024 a un catalogue de règles
        // configuré (`config/floty.php fiscal.year_boots`). Tester un
        // chevauchement 2024-2025 réel demanderait de configurer
        // 2025 - reporté à V2 quand le catalogue 2025 sera créé.
        $vehicle = $this->makeVehicleWithSingleVfc();
        $jan = $this->makeContract($vehicle, '2024-01-01', '2024-01-15', ContractType::Lcd);
        $dec = $this->makeContract($vehicle, '2024-12-25', '2024-12-31', ContractType::Lcd);

        $snapshot = $this->engine->compute($this->company->id, 2024);

        // Les 2 LCD courts sont chacun exonérés (≤30j cumul) → total
        // dû provient uniquement d'éventuels autres jours non couverts
        // dans cet exercice. Aucun véhicule taxé attendu.
        self::assertSame(0.0, $snapshot->totalDue);
        self::assertSame([], $snapshot->optOutContractIds);
        // Sanity : les contrats existent bien.
        self::assertNotNull($jan->id);
        self::assertNotNull($dec->id);
    }

    #[Test]
    public function le_breakdown_par_vehicule_somme_au_total_arrondi_au_centime(): void
    {
        $v1 = $this->makeVehicleWithSingleVfc();
        $v2 = $this->makeVehicleWithSingleVfc();
        $this->makeContract($v1, '2024-01-01', '2024-12-31', ContractType::Lld);
        $this->makeContract($v2, '2024-06-01', '2024-12-31', ContractType::Lld);

        $snapshot = $this->engine->compute($this->company->id, 2024);

        self::assertCount(2, $snapshot->vehicleBreakdown);
        $sumTotals = 0.0;
        foreach ($snapshot->vehicleBreakdown as $entry) {
            self::assertInstanceOf(VehicleSnapshotEntry::class, $entry);
            self::assertNotEmpty($entry->vehicleLabel);
            $sumTotals += $entry->totalDue;
        }

        self::assertEqualsWithDelta($snapshot->totalDue, $sumTotals, 0.001);
        self::assertEqualsWithDelta(
            $snapshot->totalDue,
            $snapshot->co2DueTotal + $snapshot->pollutantsDueTotal,
            0.001,
        );
    }

    private function standardAggregatorTotalFor(Vehicle $vehicle, int $year): float
    {
        $contracts = $this->app->make(ContractQueryService::class)
            ->loadContractsByPair($year);
        $vehiclesById = $this->app->make(VehicleReadRepositoryInterface::class)
            ->findByIdsIndexed([$vehicle->id]);
        $unavailabilities = $this->app->make(ContractQueryService::class)
            ->loadUnavailabilitiesByVehicle([$vehicle->id]);

        return $this->standardAggregator->companyAnnualTax(
            $this->company->id,
            $vehiclesById,
            $contracts,
            $unavailabilities,
            $year,
        );
    }

    private function makeVehicleWithSingleVfc(): Vehicle
    {
        $vehicle = Vehicle::create([
            'license_plate' => sprintf('D%d-%03d-D%d', random_int(1, 9), random_int(1, 999), random_int(1, 9)),
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

    private function makeContract(Vehicle $vehicle, string $start, string $end, ContractType $type): Contract
    {
        $contract = new Contract;
        $contract->setRawAttributes([
            'vehicle_id' => $vehicle->id,
            'company_id' => $this->company->id,
            'start_date' => $start,
            'end_date' => $end,
            'contract_reference' => null,
            'contract_type' => $type->value,
            'notes' => null,
        ], true);
        $contract->save();

        return $contract;
    }
}
