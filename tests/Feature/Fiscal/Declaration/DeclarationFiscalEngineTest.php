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
use App\Fiscal\ValueObjects\ContractSnapshotEntry;
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
        self::assertSame([], $snapshot->contractBreakdown);
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
        // 2 contrats LCD pour un véhicule unique = 2 entries dans
        // le breakdown par contrat.
        self::assertCount(2, $snapshot->contractBreakdown);
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

        // Chaîne 2 · 2 LCD courts en juin (cluster B). Les 2 chaînes
        // restent distinctes parce que l'intervalle direct LCD-LCD
        // entre a2 (fin 02-09) et b1 (début 06-01) dépasse largement
        // max_interval=15 (phase D5.10.Q · les LLD sont ignorés, c'est
        // l'intervalle LCD direct qui décide).
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
    public function le_breakdown_par_contrat_somme_au_total_arrondi_au_centime(): void
    {
        $v1 = $this->makeVehicleWithSingleVfc();
        $v2 = $this->makeVehicleWithSingleVfc();
        $this->makeContract($v1, '2024-01-01', '2024-12-31', ContractType::Lld);
        $this->makeContract($v2, '2024-06-01', '2024-12-31', ContractType::Lld);

        $snapshot = $this->engine->compute($this->company->id, 2024);

        // 1 contrat par véhicule × 2 véhicules = 2 entries dans le
        // breakdown par contrat (D5.8).
        self::assertCount(2, $snapshot->contractBreakdown);
        $sumTotals = 0.0;
        foreach ($snapshot->contractBreakdown as $entry) {
            self::assertInstanceOf(ContractSnapshotEntry::class, $entry);
            self::assertNotEmpty($entry->vehicleLabel);
            self::assertNotEmpty($entry->vehicleFiscalSummary);
            $sumTotals += $entry->totalDue;
        }

        self::assertEqualsWithDelta($snapshot->totalDue, $sumTotals, 0.01);
        self::assertEqualsWithDelta(
            $snapshot->totalDue,
            $snapshot->co2DueTotal + $snapshot->pollutantsDueTotal,
            0.001,
        );
    }

    #[Test]
    public function le_breakdown_par_contrat_enrichit_chaque_entry_avec_son_cluster_si_applicable(): void
    {
        $vehicle = $this->makeVehicleWithSingleVfc();
        $c1 = $this->makeContract($vehicle, '2024-01-01', '2024-01-20', ContractType::Lcd);
        $c2 = $this->makeContract($vehicle, '2024-01-26', '2024-02-14', ContractType::Lcd);

        $snapshot = $this->engine->compute($this->company->id, 2024);

        // Les 2 LCD doivent former 1 cluster → fingerprint identique
        // sur les 2 entries, autres contrats hors cluster restent null.
        $byContractId = [];
        foreach ($snapshot->contractBreakdown as $entry) {
            $byContractId[$entry->contractId] = $entry;
        }
        self::assertNotNull($byContractId[$c1->id]->clusterFingerprint);
        self::assertSame(
            $byContractId[$c1->id]->clusterFingerprint,
            $byContractId[$c2->id]->clusterFingerprint,
            'Les 2 contrats du même cluster doivent partager le fingerprint.',
        );
    }

    #[Test]
    public function lcd_court_conserve_a_taxe_zero_sur_le_breakdown_par_contrat(): void
    {
        // Phase 13 D5.10.R · un contrat LCD ≤ 30 jours est exonéré
        // R-2024-021 individuellement. La répartition de la taxe couple
        // doit pondérer par les jours taxables (= 0 pour LCD exempté).
        // Sans cette pondération, le LCD recevait illégitimement une
        // part de taxe au prorata des jours bruts.
        $vehicle = $this->makeVehicleWithSingleVfc();
        $lcd = $this->makeContract($vehicle, '2024-01-01', '2024-01-20', ContractType::Lcd); // 20j exempté
        $lld = $this->makeContract($vehicle, '2024-02-01', '2024-12-31', ContractType::Lld);

        $snapshot = $this->engine->compute($this->company->id, 2024);

        $byContractId = [];
        foreach ($snapshot->contractBreakdown as $entry) {
            $byContractId[$entry->contractId] = $entry;
        }

        // Le LCD exempté doit afficher 0€ (pas de part de taxe).
        self::assertSame(0.0, $byContractId[$lcd->id]->co2Due);
        self::assertSame(0.0, $byContractId[$lcd->id]->pollutantsDue);
        self::assertSame(0.0, $byContractId[$lcd->id]->totalDue);

        // Le LLD reçoit l'intégralité de la taxe couple · sa part
        // somme au total dû.
        self::assertGreaterThan(0.0, $byContractId[$lld->id]->totalDue);
        self::assertEqualsWithDelta(
            $snapshot->totalDue,
            $byContractId[$lld->id]->totalDue,
            0.01,
            'La taxe couple est entièrement allouée au LLD (le LCD est exempté).',
        );
    }

    #[Test]
    public function lcd_court_requalifie_recupere_sa_part_de_taxe(): void
    {
        // Phase 13 D5.10.R · un contrat LCD requalifié (opt-out
        // R-2024-021 via décision Requalified) perd l'exonération · ses
        // jours redeviennent taxables et il reçoit sa part au prorata.
        $vehicle = $this->makeVehicleWithSingleVfc();
        $lcd1 = $this->makeContract($vehicle, '2024-01-01', '2024-01-20', ContractType::Lcd);
        $lcd2 = $this->makeContract($vehicle, '2024-01-26', '2024-02-14', ContractType::Lcd);
        $lld = $this->makeContract($vehicle, '2024-03-01', '2024-12-31', ContractType::Lld);

        // Persister la décision Requalified · les LCD sont opt-out.
        $clusters = $this->app->make(RiskDetectionService::class)
            ->detectClusters($this->company->id, 2024);
        self::assertCount(1, $clusters);

        FiscalReviewDecision::factory()->create([
            'company_id' => $this->company->id,
            'fiscal_year' => 2024,
            'cluster_fingerprint' => $clusters[0]->fingerprint,
            'risk_code' => $clusters[0]->code,
            'decision' => ReviewDecisionType::Requalified,
            'justification' => 'Requalifié pour test.',
            'decided_at' => CarbonImmutable::now(),
        ]);

        $snapshot = $this->engine->compute($this->company->id, 2024);

        $byContractId = [];
        foreach ($snapshot->contractBreakdown as $entry) {
            $byContractId[$entry->contractId] = $entry;
        }

        // Les LCD requalifiés perdent l'exonération et reçoivent une
        // part de taxe non-zéro.
        self::assertGreaterThan(0.0, $byContractId[$lcd1->id]->totalDue);
        self::assertGreaterThan(0.0, $byContractId[$lcd2->id]->totalDue);
        self::assertGreaterThan(0.0, $byContractId[$lld->id]->totalDue);

        // La somme des parts est égale au total dû (à l'arrondi près).
        $sumParts = $byContractId[$lcd1->id]->totalDue
            + $byContractId[$lcd2->id]->totalDue
            + $byContractId[$lld->id]->totalDue;
        self::assertEqualsWithDelta($snapshot->totalDue, $sumParts, 0.02);
    }

    #[Test]
    public function requalified_avec_contrat_exclu_garde_son_exoneration_lcd(): void
    {
        // Phase 13 D5.10.S · décision Requalified avec un contrat
        // explicitement exclu · le contrat exclu reste LCD exempté
        // R-2024-021, les autres LCD du cluster opt-out comme prévu.
        $vehicle = $this->makeVehicleWithSingleVfc();
        $c1 = $this->makeContract($vehicle, '2024-01-01', '2024-01-20', ContractType::Lcd);
        $c2 = $this->makeContract($vehicle, '2024-01-26', '2024-02-14', ContractType::Lcd);
        $this->makeContract($vehicle, '2024-03-01', '2024-12-31', ContractType::Lld);

        $clusters = $this->app->make(RiskDetectionService::class)
            ->detectClusters($this->company->id, 2024);
        self::assertCount(1, $clusters);

        FiscalReviewDecision::factory()->create([
            'company_id' => $this->company->id,
            'fiscal_year' => 2024,
            'cluster_fingerprint' => $clusters[0]->fingerprint,
            'risk_code' => $clusters[0]->code,
            'decision' => ReviewDecisionType::Requalified,
            'justification' => 'Test exclusion partielle.',
            'excluded_contract_ids' => [$c1->id], // exclure c1
            'decided_at' => CarbonImmutable::now(),
        ]);

        $snapshot = $this->engine->compute($this->company->id, 2024);

        // c1 est exclu · pas d'opt-out · garde son exonération R-2024-021
        self::assertNotContains($c1->id, $snapshot->optOutContractIds);
        // c2 reste opt-out (dans le cluster, pas exclu, décision Requalified)
        self::assertContains($c2->id, $snapshot->optOutContractIds);

        // L'AppliedDecisionEntry trace les exclusions.
        self::assertCount(1, $snapshot->appliedDecisions);
        self::assertSame([$c1->id], $snapshot->appliedDecisions[0]->excludedContractIds);
    }

    #[Test]
    public function contrat_exclu_du_cluster_n_a_pas_de_cluster_fingerprint_dans_le_snapshot(): void
    {
        // Phase 13 D5.10.S · un contrat exclu sort visuellement du
        // bloc cluster côté frontend · son ContractSnapshotEntry doit
        // avoir clusterFingerprint=null pour être rendu comme row simple.
        $vehicle = $this->makeVehicleWithSingleVfc();
        $c1 = $this->makeContract($vehicle, '2024-01-01', '2024-01-20', ContractType::Lcd);
        $c2 = $this->makeContract($vehicle, '2024-01-26', '2024-02-14', ContractType::Lcd);

        $clusters = $this->app->make(RiskDetectionService::class)
            ->detectClusters($this->company->id, 2024);
        self::assertCount(1, $clusters);

        FiscalReviewDecision::factory()->create([
            'company_id' => $this->company->id,
            'fiscal_year' => 2024,
            'cluster_fingerprint' => $clusters[0]->fingerprint,
            'risk_code' => $clusters[0]->code,
            'decision' => ReviewDecisionType::Conserved,
            'justification' => 'Test marker.',
            'excluded_contract_ids' => [$c1->id],
            'decided_at' => CarbonImmutable::now(),
        ]);

        $snapshot = $this->engine->compute($this->company->id, 2024);

        $byContractId = [];
        foreach ($snapshot->contractBreakdown as $entry) {
            $byContractId[$entry->contractId] = $entry;
        }

        // c1 exclu · pas de mapping cluster · fingerprint null
        self::assertNull($byContractId[$c1->id]->clusterFingerprint);
        // c2 toujours dans le cluster · fingerprint set
        self::assertNotNull($byContractId[$c2->id]->clusterFingerprint);
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
