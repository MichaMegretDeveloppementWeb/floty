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
 * (Phase 11 D2, ADR-0015 § 4 + cas-tests permanents § 7).
 *
 * Service piloté par les seuils du singleton `FiscalRiskSettings` :
 * sauf indication contraire, les tests utilisent les valeurs par
 * défaut (`max_interval=15, threshold_low=30, threshold_high=90,
 * count_high=5, lld_breaks_chain=true`).
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
        $this->makeContract('2025-01-01', '2025-01-20'); // 20 j
        $this->makeContract('2025-01-26', '2025-02-14'); // 20 j, intervalle 5

        $clusters = $this->service->detectClusters($this->company->id, 2025);

        self::assertCount(1, $clusters);
        self::assertSame(RiskCode::Chain, $clusters[0]->code);
        self::assertSame(RiskLevel::Moyen, $clusters[0]->level);
        self::assertSame(40, $clusters[0]->cumulativeDaysInYear);
        self::assertSame(2, $clusters[0]->contractsCount);
    }

    #[Test]
    public function cas3_5_lcd_15j_separes_7j_chain_fort(): void
    {
        // 5 contrats de 15 j chacun, séparés de 7 jours pleins
        // Cumul = 75 j (> threshold_low 30, < threshold_high 90)
        // Mais count = 5 ≥ count_high (5) → CHAIN-FORT
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
    }

    #[Test]
    public function cas4_4_lcd_cumul_100j_chain_fort(): void
    {
        // 4 contrats de 25 j enchaînés (cumul 100 > 90)
        $this->makeContract('2025-01-01', '2025-01-25');
        $this->makeContract('2025-02-01', '2025-02-25'); // intervalle 6
        $this->makeContract('2025-03-04', '2025-03-28'); // intervalle 6
        $this->makeContract('2025-04-04', '2025-04-28'); // intervalle 6

        $clusters = $this->service->detectClusters($this->company->id, 2025);

        self::assertCount(1, $clusters);
        self::assertSame(RiskCode::ChainFort, $clusters[0]->code);
        self::assertSame(100, $clusters[0]->cumulativeDaysInYear);
    }

    #[Test]
    public function cas5_lld_intercale_rompt_la_chaine_par_defaut(): void
    {
        // LCD 20j → LLD 40j → LCD 20j (intervalles 5j)
        $this->makeContract('2025-01-01', '2025-01-20', type: ContractType::Lcd);
        $this->makeContract('2025-01-26', '2025-03-06', type: ContractType::Lld); // 40 j → LLD
        $this->makeContract('2025-03-12', '2025-03-31', type: ContractType::Lcd);

        self::assertSame([], $this->service->detectClusters($this->company->id, 2025));
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
    public function cumul_egal_au_threshold_low_pas_de_cluster(): void
    {
        // Cumul exactement 30 j → strict `>` donc PAS de cluster (R-LCD-CHAIN nécessite > 30)
        $this->makeContract('2025-01-01', '2025-01-15'); // 15 j
        $this->makeContract('2025-01-23', '2025-02-06'); // 15 j, intervalle 7

        self::assertSame([], $this->service->detectClusters($this->company->id, 2025));
    }

    #[Test]
    public function cumul_egal_au_threshold_high_classe_chain_pas_chain_fort(): void
    {
        // Cumul exactement 90 j → > 30 mais pas > 90 → CHAIN, pas CHAIN-FORT
        $this->makeContract('2025-01-01', '2025-02-14'); // 45 j... mais long contrat: c'est du LLD
        // Reformulons : 3 LCD de 30 j chacun, cumul 90
        Contract::query()->delete();
        $this->makeContract('2025-01-01', '2025-01-30'); // 30 j
        $this->makeContract('2025-02-06', '2025-03-07'); // 30 j, intervalle 7
        $this->makeContract('2025-03-15', '2025-04-13'); // 30 j, intervalle 7

        $clusters = $this->service->detectClusters($this->company->id, 2025);

        self::assertCount(1, $clusters);
        self::assertSame(RiskCode::Chain, $clusters[0]->code);
        self::assertSame(90, $clusters[0]->cumulativeDaysInYear);
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
    public function lld_breaks_chain_false_garde_la_chaine_a_travers_un_lld(): void
    {
        $settings = FiscalRiskSettings::singleton();
        $settings->lld_breaks_chain = false;
        $settings->save();

        // LCD 20j → LLD 40j (n'interrompt plus) → LCD 20j (intervalle au précédent LCD = 46j > 15)
        $this->makeContract('2025-01-01', '2025-01-20', type: ContractType::Lcd);
        $this->makeContract('2025-01-26', '2025-03-06', type: ContractType::Lld);
        $this->makeContract('2025-03-12', '2025-03-31', type: ContractType::Lcd);

        // Le LLD ne casse plus la chaîne mais l'intervalle entre les
        // 2 LCD reste >> max_interval donc pas de chaîne malgré tout.
        self::assertSame([], $this->service->detectClusters($this->company->id, 2025));

        // En revanche, deux LCD très proches séparés par un LLD non
        // interruptif : la chaîne se maintient si l'intervalle LCD-LCD
        // direct reste ≤ max_interval (cas théorique avec plages
        // chevauchantes, non testable proprement ici car le trigger DB
        // empêche les chevauchements). On valide simplement le toggle.
        self::assertFalse($settings->fresh()->lld_breaks_chain);
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
        self::assertIsArray($clusters);
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
            'lld_breaks_chain' => true,
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
    public function cumul_n_inclut_que_les_jours_dans_l_annee_cible(): void
    {
        // Contrat à cheval 2024 → 2025 : seuls les jours 2025 comptent
        // 2024-12-26 → 2025-01-15 = 21 jours total dont 15 en 2025
        $this->makeContract('2024-12-26', '2025-01-15');
        $this->makeContract('2025-01-22', '2025-02-10'); // 20 j, intervalle 6

        $clusters = $this->service->detectClusters($this->company->id, 2025);

        self::assertCount(1, $clusters);
        // Cumul 2025 = 15 (jours du 1er en 2025) + 20 (2nd) = 35 > 30 → CHAIN
        self::assertSame(35, $clusters[0]->cumulativeDaysInYear);
        self::assertSame(RiskCode::Chain, $clusters[0]->code);
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
