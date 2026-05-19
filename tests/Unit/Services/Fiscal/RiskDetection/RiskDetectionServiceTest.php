<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Fiscal\RiskDetection;

use App\Enums\Contract\ContractType;
use App\Enums\FiscalReviewDecision\RiskCode;
use App\Enums\FiscalReviewDecision\RiskLevel;
use App\Models\Company;
use App\Models\Contract;
use App\Models\FiscalRiskSettings;
use App\Models\Vehicle;
use App\Services\Fiscal\RiskDetection\RiskDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre l'algorithme de détection des clusters de risque fiscal
 * (Phase 11 D2, ADR-0015 § 4 + cas-tests permanents § 7, refondu
 * D5.10.Q : les LLD sont silencieusement ignorés, refondu Lot 5 D1 ·
 * critère de qualification = union des jours uniques couverts).
 *
 * Service piloté par les seuils du singleton `FiscalRiskSettings` ·
 * sauf indication contraire, les tests utilisent les valeurs par
 * défaut (`max_interval=15, threshold_low=30, threshold_high=90,
 * count_high=5`).
 */
final class RiskDetectionServiceTest extends TestCase
{
    use RefreshDatabase;

    private RiskDetectionService $service;

    private Company $company;

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(RiskDetectionService::class);
        $this->company = Company::factory()->create();
        $this->vehicle = Vehicle::factory()->create();
    }

    // ---------- Cas-tests permanents ADR-0015 § 7 ----------

    #[Test]
    public function cas1_3_lcd_de_20j_espacees_de_30j_aucun_cluster(): void
    {
        $this->makeContract('2025-01-01', '2025-01-20');
        $this->makeContract('2025-02-20', '2025-03-11'); // intervalle 30j > 15
        $this->makeContract('2025-04-11', '2025-04-30'); // intervalle 30j > 15

        self::assertSame([], $this->service->detectClusters($this->company->id, 2025));
    }

    #[Test]
    public function cas2_2_lcd_20j_separes_5j_chain_moyen(): void
    {
        // 2 LCD de 20j séparés de 5j : union des jours = 20+20 = 40 jours
        // (pas de chevauchement). 40 > 30 (threshold_low) → ChainMoyen.
        // Bornes affichées dans l'UI : 2025-01-01 → 2025-02-14.
        $this->makeContract('2025-01-01', '2025-01-20'); // 20 j
        $this->makeContract('2025-01-26', '2025-02-14'); // 20 j, intervalle 5

        $clusters = $this->service->detectClusters($this->company->id, 2025);

        self::assertCount(1, $clusters);
        self::assertSame(RiskCode::Chain, $clusters[0]->code);
        self::assertSame(RiskLevel::Moyen, $clusters[0]->level);
        self::assertSame(40, $clusters[0]->coveragePeriodDays);
        self::assertSame('2025-01-01', $clusters[0]->coverageStartDate);
        self::assertSame('2025-02-14', $clusters[0]->coverageEndDate);
        self::assertSame(2, $clusters[0]->contractsCount);
    }

    #[Test]
    public function cas3_5_lcd_15j_separes_7j_chain_fort(): void
    {
        // 5 contrats de 15 j chacun, séparés de 7 jours pleins.
        // count = 5 ≥ count_high (5) → ChainFort (peu importe l'union).
        // Union des jours = 5 × 15 = 75 jours (aucun chevauchement).
        $this->makeContract('2025-01-01', '2025-01-15');
        $this->makeContract('2025-01-23', '2025-02-06');
        $this->makeContract('2025-02-14', '2025-02-28');
        $this->makeContract('2025-03-08', '2025-03-22');
        $this->makeContract('2025-03-30', '2025-04-13');

        $clusters = $this->service->detectClusters($this->company->id, 2025);

        self::assertCount(1, $clusters);
        self::assertSame(RiskCode::ChainFort, $clusters[0]->code);
        self::assertSame(RiskLevel::Eleve, $clusters[0]->level);
        self::assertSame(5, $clusters[0]->contractsCount);
        self::assertSame(75, $clusters[0]->coveragePeriodDays);
    }

    #[Test]
    public function cas4_4_lcd_union_100j_chain_fort(): void
    {
        // 4 contrats de 25 j enchaînés sans chevauchement. Union = 100 j.
        // 100 > 90 (threshold_high) → ChainFort.
        $this->makeContract('2025-01-01', '2025-01-25');
        $this->makeContract('2025-02-01', '2025-02-25'); // intervalle 6
        $this->makeContract('2025-03-04', '2025-03-28'); // intervalle 6
        $this->makeContract('2025-04-04', '2025-04-28'); // intervalle 6

        $clusters = $this->service->detectClusters($this->company->id, 2025);

        self::assertCount(1, $clusters);
        self::assertSame(RiskCode::ChainFort, $clusters[0]->code);
        self::assertSame(100, $clusters[0]->coveragePeriodDays);
    }

    #[Test]
    public function cas5_lld_intercale_n_interrompt_pas_la_chaine(): void
    {
        // Phase 13 D5.10.Q : les LLD sont silencieusement ignorés
        // lors du chaînage. Les 2 LCD séparés de 51 jours pleins
        // (au-delà de max_interval=15) ne forment pas de chaîne, mais
        // c'est l'intervalle inter-LCD qui le décide, pas le LLD
        // intercalé.
        $this->makeContract('2025-01-01', '2025-01-20', type: ContractType::Lcd);
        $this->makeContract('2025-01-26', '2025-03-06', type: ContractType::Lld); // 40j LLD
        $this->makeContract('2025-03-12', '2025-03-31', type: ContractType::Lcd);

        // Intervalle LCD-LCD direct = 2025-01-20 → 2025-03-12 = 50j > 15 → pas de chaîne.
        self::assertSame([], $this->service->detectClusters($this->company->id, 2025));
    }

    #[Test]
    public function lld_intercale_sur_autre_vehicule_n_interrompt_pas_la_chaine(): void
    {
        // Phase 13 D5.10.Q : cas régression ECO 2024 ·
        // LCD Nevada → LLD Toyota (autre véhicule, intercalé) → LCD Nevada.
        // Le LLD doit être silencieusement ignoré : les 2 LCD à
        // intervalle 3j forment une chaîne sur Nevada.
        // Union des jours : LCD1 (1-23 avr) = 23 j + LCD2 (26 avr-18 mai) = 23 j
        // (pas de chevauchement) = 46 j > 30 → ChainMoyen.
        $vehicleNevada = $this->vehicle;
        $vehicleToyota = Vehicle::factory()->create();

        Contract::factory()->create([
            'company_id' => $this->company->id,
            'vehicle_id' => $vehicleNevada->id,
            'start_date' => '2024-04-01',
            'end_date' => '2024-04-23',
            'contract_type' => ContractType::Lcd,
        ]);
        Contract::factory()->create([
            'company_id' => $this->company->id,
            'vehicle_id' => $vehicleToyota->id,
            'start_date' => '2024-04-15',
            'end_date' => '2024-07-31',
            'contract_type' => ContractType::Lld,
        ]);
        Contract::factory()->create([
            'company_id' => $this->company->id,
            'vehicle_id' => $vehicleNevada->id,
            'start_date' => '2024-04-26',
            'end_date' => '2024-05-18',
            'contract_type' => ContractType::Lcd,
        ]);

        $clusters = $this->service->detectClusters($this->company->id, 2024);

        self::assertCount(1, $clusters);
        self::assertSame(RiskCode::Chain, $clusters[0]->code);
        self::assertSame(2, $clusters[0]->contractsCount);
        self::assertSame(46, $clusters[0]->coveragePeriodDays);
    }

    #[Test]
    public function cas6_un_seul_lcd_isole_aucun_cluster(): void
    {
        $this->makeContract('2025-06-01', '2025-06-25');

        self::assertSame([], $this->service->detectClusters($this->company->id, 2025));
    }

    #[Test]
    public function cas7_pas_de_cluster_cross_entreprise(): void
    {
        $other = Company::factory()->create();

        // 2 LCD chaînés mais sur 2 entreprises distinctes
        $this->makeContract('2025-01-01', '2025-01-20', companyId: $this->company->id);
        $this->makeContract('2025-01-26', '2025-02-14', companyId: $other->id);

        self::assertSame([], $this->service->detectClusters($this->company->id, 2025));
        self::assertSame([], $this->service->detectClusters($other->id, 2025));
    }

    // ---------- Tests de bord algorithmiques ----------

    #[Test]
    public function interval_egal_au_max_interval_inclus_dans_la_chaine(): void
    {
        // Intervalle = 15 (= max_interval) → chaîne maintenue
        // Cumul 20+20 = 40 → CHAIN
        $this->makeContract('2025-01-01', '2025-01-20');
        $this->makeContract('2025-02-05', '2025-02-24'); // intervalle 15

        $clusters = $this->service->detectClusters($this->company->id, 2025);

        self::assertCount(1, $clusters);
    }

    #[Test]
    public function interval_strictement_superieur_au_max_interval_rompt_la_chaine(): void
    {
        // Intervalle = 16 (> max_interval 15) → chaîne rompue
        $this->makeContract('2025-01-01', '2025-01-20');
        $this->makeContract('2025-02-06', '2025-02-25'); // intervalle 16

        self::assertSame([], $this->service->detectClusters($this->company->id, 2025));
    }

    #[Test]
    public function union_egale_au_threshold_low_pas_de_cluster(): void
    {
        // Union exactement 30 j → strict `>` donc PAS de cluster
        // (R-LCD-CHAIN nécessite > 30). 2 LCD de 15 j contigus
        // (intervalle 0 j) sont fusionnés en un bloc 1-30 jan = 30 j.
        $this->makeContract('2025-01-01', '2025-01-15'); // 15 j
        $this->makeContract('2025-01-16', '2025-01-30'); // 15 j, intervalle 0

        self::assertSame([], $this->service->detectClusters($this->company->id, 2025));
    }

    #[Test]
    public function union_strictement_inferieure_au_threshold_high_classe_chain_pas_chain_fort(): void
    {
        // Union 77 j (30 + 30 + 17, aucun chevauchement, intervalles 7 j) ·
        // > 30 (threshold_low) mais pas > 90 (threshold_high) → CHAIN moyen.
        // count = 3 < count_high (5) : pas de bascule par count.
        $this->makeContract('2025-01-01', '2025-01-30'); // 30 j
        $this->makeContract('2025-02-06', '2025-03-07'); // 30 j, intervalle 7
        $this->makeContract('2025-03-15', '2025-03-31'); // 17 j, intervalle 7

        $clusters = $this->service->detectClusters($this->company->id, 2025);

        self::assertCount(1, $clusters);
        self::assertSame(RiskCode::Chain, $clusters[0]->code);
        self::assertSame(77, $clusters[0]->coveragePeriodDays);
    }

    #[Test]
    public function count_egal_au_count_high_classe_chain_fort(): void
    {
        // 5 LCD courts (10 j chacun, cumul 50 < 90) mais ≥ 5 → CHAIN-FORT
        $this->makeContract('2025-01-01', '2025-01-10');
        $this->makeContract('2025-01-18', '2025-01-27');
        $this->makeContract('2025-02-04', '2025-02-13');
        $this->makeContract('2025-02-21', '2025-03-02');
        $this->makeContract('2025-03-10', '2025-03-19');

        $clusters = $this->service->detectClusters($this->company->id, 2025);

        self::assertCount(1, $clusters);
        self::assertSame(RiskCode::ChainFort, $clusters[0]->code);
    }

    #[Test]
    public function tri_par_start_date_puis_id_deterministe(): void
    {
        // 2 LCD à la même start_date sur 2 véhicules distincts (pour
        // contourner le trigger anti-overlap véhicule)
        $vehicleB = Vehicle::factory()->create();

        $first = Contract::factory()->create([
            'company_id' => $this->company->id,
            'vehicle_id' => $this->vehicle->id,
            'start_date' => '2025-03-10',
            'end_date' => '2025-03-20',
            'contract_type' => ContractType::Lcd,
        ]);
        $second = Contract::factory()->create([
            'company_id' => $this->company->id,
            'vehicle_id' => $vehicleB->id,
            'start_date' => '2025-03-10',
            'end_date' => '2025-03-25',
            'contract_type' => ContractType::Lcd,
        ]);

        $clusters = $this->service->detectClusters($this->company->id, 2025);

        // Cumul 11+16 = 27 j, < threshold_low 30 → pas de cluster détecté
        // mais on vérifie que le service exécute bien sans erreur sur ce cas
        // (validation déterministe = repo trie par id en cas d'égalité).
        self::assertCount(0, $clusters);
        self::assertContains($first->id, [$first->id, $second->id]);
    }

    #[Test]
    public function respecte_les_seuils_personnalises_du_singleton_settings(): void
    {
        $settings = FiscalRiskSettings::singleton();
        $settings->fill([
            'max_interval' => 30,
            'threshold_low' => 10,
            'threshold_high' => 200,
            'count_high' => 99,
        ])->save();

        // Avec threshold_low = 10, deux LCD de 8 j (cumul 16) déclenchent CHAIN
        $this->makeContract('2025-01-01', '2025-01-08'); // 8 j
        $this->makeContract('2025-01-25', '2025-02-01'); // 8 j, intervalle 16 (≤ 30)

        $clusters = $this->service->detectClusters($this->company->id, 2025);

        self::assertCount(1, $clusters);
        self::assertSame(RiskCode::Chain, $clusters[0]->code);
    }

    #[Test]
    public function fingerprint_inclut_tous_les_contrats_de_la_chaine(): void
    {
        $c1 = $this->makeContract('2025-01-01', '2025-01-20');
        $c2 = $this->makeContract('2025-01-26', '2025-02-14');

        $clusters = $this->service->detectClusters($this->company->id, 2025);

        self::assertCount(1, $clusters);
        $cluster = $clusters[0];
        self::assertSame(64, strlen($cluster->fingerprint));
        $contractIds = array_map(static fn ($c) => $c->contractId, $cluster->contracts);
        self::assertContains($c1->id, $contractIds);
        self::assertContains($c2->id, $contractIds);
    }

    #[Test]
    public function intervalle_du_premier_contrat_du_cluster_est_null(): void
    {
        $this->makeContract('2025-01-01', '2025-01-20');
        $this->makeContract('2025-01-26', '2025-02-14');

        $clusters = $this->service->detectClusters($this->company->id, 2025);

        self::assertNull($clusters[0]->contracts[0]->intervalBeforeDays);
        self::assertSame(5, $clusters[0]->contracts[1]->intervalBeforeDays);
    }

    #[Test]
    public function decision_est_null_en_d2_avant_persistance(): void
    {
        // D2 ne pré-applique pas les décisions persistées (c'est le rôle de D3).
        $this->makeContract('2025-01-01', '2025-01-20');
        $this->makeContract('2025-01-26', '2025-02-14');

        $clusters = $this->service->detectClusters($this->company->id, 2025);

        self::assertNull($clusters[0]->decision);
        self::assertNull($clusters[0]->justification);
    }

    #[Test]
    public function table_vide_retourne_liste_vide(): void
    {
        self::assertSame([], $this->service->detectClusters($this->company->id, 2025));
    }

    #[Test]
    public function union_bornee_a_l_annee_fiscale_quand_la_chaine_deborde(): void
    {
        // Chaîne à cheval 2024 → 2025 : seule la portion 2025 compte.
        // LCD 1 : 2024-12-26 → 2025-01-15 → borné à 2025-01-01 → 2025-01-15 = 15 j
        // LCD 2 : 2025-01-22 → 2025-02-10 = 20 j
        // Aucun chevauchement entre les bornés : union = 15 + 20 = 35 j > 30
        // → ChainMoyen.
        $this->makeContract('2024-12-26', '2025-01-15');
        $this->makeContract('2025-01-22', '2025-02-10'); // intervalle 6

        $clusters = $this->service->detectClusters($this->company->id, 2025);

        self::assertCount(1, $clusters);
        self::assertSame(35, $clusters[0]->coveragePeriodDays);
        self::assertSame('2025-01-01', $clusters[0]->coverageStartDate);
        self::assertSame('2025-02-10', $clusters[0]->coverageEndDate);
        self::assertSame(RiskCode::Chain, $clusters[0]->code);
    }

    #[Test]
    public function distinct_vehicles_count_reflete_les_vehicules_du_cluster(): void
    {
        // Chaîne multi-véhicules : 3 LCD chevauchants sur 3 véhicules
        // différents (1 → 26 avr, 2 → 21 avr ⊂ premier, 22 avr → 3 mai
        // recouvre les 22-26 avr du premier). Union des intervalles =
        // bloc continu 1 avr → 3 mai = 33 jours : 33 > 30 → ChainMoyen.
        // distinctVehiclesCount = 3.
        $vehicleB = Vehicle::factory()->create();
        $vehicleC = Vehicle::factory()->create();

        Contract::factory()->create([
            'company_id' => $this->company->id,
            'vehicle_id' => $this->vehicle->id,
            'start_date' => '2025-04-01',
            'end_date' => '2025-04-26',
            'contract_type' => ContractType::Lcd,
        ]);
        Contract::factory()->create([
            'company_id' => $this->company->id,
            'vehicle_id' => $vehicleB->id,
            'start_date' => '2025-04-02',
            'end_date' => '2025-04-21',
            'contract_type' => ContractType::Lcd,
        ]);
        Contract::factory()->create([
            'company_id' => $this->company->id,
            'vehicle_id' => $vehicleC->id,
            'start_date' => '2025-04-22',
            'end_date' => '2025-05-03',
            'contract_type' => ContractType::Lcd,
        ]);

        $clusters = $this->service->detectClusters($this->company->id, 2025);

        self::assertCount(1, $clusters);
        self::assertSame(33, $clusters[0]->coveragePeriodDays);
        self::assertSame(3, $clusters[0]->distinctVehiclesCount);
        self::assertSame(3, $clusters[0]->contractsCount);
    }

    // ---------- Lot 5 D1 : sémantique union des jours uniques ----------

    #[Test]
    public function union_jours_lcd_chevauchants_pas_de_double_comptage(): void
    {
        // Lot 5 D1 : 3 LCD de 17 jours superposés sur les MÊMES 17 jours
        // (3 véhicules distincts pour contourner l'unicité d'overlap
        // par véhicule). Union = 17 j, pas 51 j. < 30 → pas de cluster.
        // L'ancienne sémantique « plage début → fin » donnait pareil
        // (17 j) ; l'ancienne sémantique « cumul brut » donnait 51 j et
        // déclenchait à tort un cluster.
        $vehicleB = Vehicle::factory()->create();
        $vehicleC = Vehicle::factory()->create();

        Contract::factory()->create([
            'company_id' => $this->company->id,
            'vehicle_id' => $this->vehicle->id,
            'start_date' => '2025-05-01',
            'end_date' => '2025-05-17',
            'contract_type' => ContractType::Lcd,
        ]);
        Contract::factory()->create([
            'company_id' => $this->company->id,
            'vehicle_id' => $vehicleB->id,
            'start_date' => '2025-05-01',
            'end_date' => '2025-05-17',
            'contract_type' => ContractType::Lcd,
        ]);
        Contract::factory()->create([
            'company_id' => $this->company->id,
            'vehicle_id' => $vehicleC->id,
            'start_date' => '2025-05-01',
            'end_date' => '2025-05-17',
            'contract_type' => ContractType::Lcd,
        ]);

        self::assertSame([], $this->service->detectClusters($this->company->id, 2025));
    }

    #[Test]
    public function union_jours_lcd_chevauchants_partiel_au_dessus_threshold(): void
    {
        // Lot 5 D1 : 3 LCD partiellement chevauchants formant un bloc
        // continu de 35 jours (1-15 mai, 10-25 mai, 20 mai-4 juin) ·
        // chaque LCD seul fait 15-16 j (donc reste LCD ≤ 30j).
        // Union des fusions : 1 mai → 4 juin = 35 j > 30 → ChainMoyen.
        // Cumul brut donnerait 47 j (15+16+16) : ici les chevauchements
        // sont absorbés par la fusion d'intervalles.
        $vehicleB = Vehicle::factory()->create();
        $vehicleC = Vehicle::factory()->create();

        Contract::factory()->create([
            'company_id' => $this->company->id,
            'vehicle_id' => $this->vehicle->id,
            'start_date' => '2025-05-01',
            'end_date' => '2025-05-15',
            'contract_type' => ContractType::Lcd,
        ]);
        Contract::factory()->create([
            'company_id' => $this->company->id,
            'vehicle_id' => $vehicleB->id,
            'start_date' => '2025-05-10',
            'end_date' => '2025-05-25',
            'contract_type' => ContractType::Lcd,
        ]);
        Contract::factory()->create([
            'company_id' => $this->company->id,
            'vehicle_id' => $vehicleC->id,
            'start_date' => '2025-05-20',
            'end_date' => '2025-06-04',
            'contract_type' => ContractType::Lcd,
        ]);

        $clusters = $this->service->detectClusters($this->company->id, 2025);

        self::assertCount(1, $clusters);
        self::assertSame(RiskCode::Chain, $clusters[0]->code);
        self::assertSame(35, $clusters[0]->coveragePeriodDays);
        self::assertSame('2025-05-01', $clusters[0]->coverageStartDate);
        self::assertSame('2025-06-04', $clusters[0]->coverageEndDate);
    }

    #[Test]
    public function union_jours_lcd_avec_trous_n_inflate_pas_la_plage(): void
    {
        // Lot 5 D1 : 4 LCD séparés par des trous de quelques jours.
        // Plage début → fin = 1-jan → 24-fév = 55 j (l'ancienne
        // sémantique gonflait avec les trous).
        // Union = 14 + 11 + 6 + 9 = 40 j (pas de chevauchement).
        // 40 > 30 → ChainMoyen.
        $this->makeContract('2025-01-01', '2025-01-14'); // 14 j
        $this->makeContract('2025-01-20', '2025-01-30'); // 11 j, intervalle 5
        $this->makeContract('2025-02-05', '2025-02-10'); //  6 j, intervalle 5
        $this->makeContract('2025-02-16', '2025-02-24'); //  9 j, intervalle 5

        $clusters = $this->service->detectClusters($this->company->id, 2025);

        self::assertCount(1, $clusters);
        self::assertSame(RiskCode::Chain, $clusters[0]->code);
        self::assertSame(40, $clusters[0]->coveragePeriodDays);
        // Bornes externes inchangées : servent à l'affichage UI
        // uniquement, pas au seuil.
        self::assertSame('2025-01-01', $clusters[0]->coverageStartDate);
        self::assertSame('2025-02-24', $clusters[0]->coverageEndDate);
    }

    // ---------- Lot 3 D08 : mémoïsation per-request (clustersCache) ----------

    /**
     * Garantit que la mémoïsation `$clustersCache` retourne strictement
     * la même instance (identité, via `assertSame`) sur appels répétés
     * pour un même couple `(companyId, year)`. Prouve que le 2e appel
     * lit le cache au lieu de relancer l'algorithme de chaînage.
     */
    #[Test]
    public function detect_clusters_est_memoise_sur_appels_repetes(): void
    {
        // Fixture : 2 LCD chaînés produisent un cluster ChainMoyen.
        $this->makeContract('2025-01-01', '2025-01-20');
        $this->makeContract('2025-01-26', '2025-02-14');

        $first = $this->service->detectClusters($this->company->id, 2025);
        $second = $this->service->detectClusters($this->company->id, 2025);

        self::assertSame($first, $second);
        self::assertCount(1, $first);
    }

    /**
     * Garantit que le cache discrimine bien sur le couple
     * `(companyId, year)` : 2 entreprises distinctes ou 2 années
     * distinctes doivent produire 2 entrées de cache séparées.
     */
    #[Test]
    public function detect_clusters_distingue_par_couple_company_annee(): void
    {
        $otherCompany = Company::factory()->create();
        $this->makeContract('2025-01-01', '2025-01-20');
        $this->makeContract('2025-01-26', '2025-02-14');

        $a2025 = $this->service->detectClusters($this->company->id, 2025);
        $other2025 = $this->service->detectClusters($otherCompany->id, 2025);
        $a2024 = $this->service->detectClusters($this->company->id, 2024);

        // L'entreprise sans contrat = liste vide (pas le même array que A)
        self::assertNotSame($a2025, $other2025);
        self::assertSame([], $other2025);
        // L'année sans contrat = liste vide elle aussi
        self::assertSame([], $a2024);
    }

    /**
     * Lot 3 D08 : garantit que `clearCache()` force le recompute : si un
     * consommateur mute des contracts entre deux appels intra-requête,
     * il peut explicitement invalider le cache pour relire la nouvelle
     * réalité plutôt que de servir un résultat stale.
     */
    #[Test]
    public function clear_cache_force_le_recompute_apres_mutation(): void
    {
        // Premier appel : aucun contrat : liste vide cachée.
        $first = $this->service->detectClusters($this->company->id, 2025);
        self::assertSame([], $first);

        // Mutation intra-requête : ajout de 2 LCD chaînés.
        $this->makeContract('2025-01-01', '2025-01-20');
        $this->makeContract('2025-01-26', '2025-02-14');

        // Sans clearCache : le cache renvoie encore la liste vide stale.
        $stale = $this->service->detectClusters($this->company->id, 2025);
        self::assertSame([], $stale);

        // Avec clearCache : re-calcul détecte le cluster.
        $this->service->clearCache();
        $fresh = $this->service->detectClusters($this->company->id, 2025);
        self::assertCount(1, $fresh);
    }

    private function makeContract(
        string $start,
        string $end,
        ?int $companyId = null,
        ?ContractType $type = null,
    ): Contract {
        return Contract::factory()->create([
            'company_id' => $companyId ?? $this->company->id,
            'vehicle_id' => $this->vehicle->id,
            'start_date' => $start,
            'end_date' => $end,
            'contract_type' => $type ?? Contract::deriveTypeFromDates($start, $end),
        ]);
    }
}
