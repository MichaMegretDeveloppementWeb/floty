<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Billing;

use App\Enums\Vehicle\VehicleExitReason;
use App\Exceptions\Billing\MissingPricingException;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Unavailability;
use App\Models\Vehicle;
use App\Models\VehicleYearlyPricing;
use App\Services\Billing\BillingCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests d'intégration du BillingCalculator (Phase 14.C facturation V1.2).
 *
 * Exécutés sur SQLite mémoire via `RefreshDatabase` plutôt qu'avec des
 * mocks de repositories : la mécanique de découpage civil + dédup des
 * jours par véhicule mérite un vrai cycle DB pour valider le pipeline
 * (cf. doctrine projet : tests Action/Service contre la vraie DB).
 *
 * Tarifs constants : 90 € / 500 € / 1 800 € (cents : 9 000 / 50 000 / 180 000).
 */
final class BillingCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private const int DAILY = 9_000;

    private const int WEEKLY = 50_000;

    private const int MONTHLY = 180_000;

    private BillingCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = $this->app->make(BillingCalculator::class);
    }

    #[Test]
    public function refuse_un_mois_hors_plage(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->calculator->calculate(companyId: 1, year: 2024, month: 13);
    }

    #[Test]
    public function retourne_zero_lignes_quand_aucun_contrat_sur_la_periode(): void
    {
        $company = Company::factory()->create();

        $result = $this->calculator->calculate($company->id, 2024, 1);

        $this->assertSame($company->id, $result->companyId);
        $this->assertSame(2024, $result->year);
        $this->assertSame(1, $result->month);
        $this->assertSame([], $result->lines);
        $this->assertSame(0, $result->totalCents);
    }

    #[Test]
    public function un_contrat_couvrant_le_mois_entier_facture_un_mois(): void
    {
        $company = Company::factory()->create();
        $vehicle = Vehicle::factory()->create(['license_plate' => 'AA-001-AA']);
        $this->createPricing($vehicle, 2024, self::DAILY, self::WEEKLY, self::MONTHLY);

        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => '2024-03-01',
            'end_date' => '2024-03-31',
        ]);

        $result = $this->calculator->calculate($company->id, 2024, 3);

        $this->assertCount(1, $result->lines);
        $line = $result->lines[0];
        $this->assertSame(31, $line->daysUsed);
        // 31 jours = 1 mois (180 000) + 1 jour (9 000) = 189 000.
        $this->assertSame(1, $line->monthsBilled);
        $this->assertSame(0, $line->weeksBilled);
        $this->assertSame(1, $line->daysBilled);
        $this->assertSame(189_000, $line->totalCents);
        $this->assertSame(189_000, $result->totalCents);
    }

    #[Test]
    public function un_contrat_partiel_debut_de_mois_clip_correctement(): void
    {
        // Contrat 25 fév → 10 mars : sur le mois de mars, on ne compte
        // que 1 → 10 = 10 jours.
        $company = Company::factory()->create();
        $vehicle = Vehicle::factory()->create(['license_plate' => 'AA-001-AA']);
        $this->createPricing($vehicle, 2024, self::DAILY, self::WEEKLY, self::MONTHLY);

        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => '2024-02-25',
            'end_date' => '2024-03-10',
        ]);

        $result = $this->calculator->calculate($company->id, 2024, 3);

        $this->assertCount(1, $result->lines);
        $line = $result->lines[0];
        $this->assertSame(10, $line->daysUsed);
        // 10 jours optimaux : 1 sem (50 000, couvre 7) + 3 jours (27 000)
        // = 77 000. Vs 10 jours (90 000). Vs 2 sem (100 000). → 77 000.
        $this->assertSame(0, $line->monthsBilled);
        $this->assertSame(1, $line->weeksBilled);
        $this->assertSame(3, $line->daysBilled);
        $this->assertSame(77_000, $line->totalCents);
    }

    #[Test]
    public function un_contrat_a_cheval_sur_deux_mois_ne_facture_que_le_mois_demande(): void
    {
        // Contrat 20 mars → 15 avril : sur avril, jours 1-15 = 15 jours.
        $company = Company::factory()->create();
        $vehicle = Vehicle::factory()->create(['license_plate' => 'AA-001-AA']);
        $this->createPricing($vehicle, 2024, self::DAILY, self::WEEKLY, self::MONTHLY);

        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => '2024-03-20',
            'end_date' => '2024-04-15',
        ]);

        $resultMarch = $this->calculator->calculate($company->id, 2024, 3);
        $resultApril = $this->calculator->calculate($company->id, 2024, 4);

        // Mars : 20 → 31 = 12 jours.
        $this->assertSame(12, $resultMarch->lines[0]->daysUsed);
        // Avril : 1 → 15 = 15 jours.
        $this->assertSame(15, $resultApril->lines[0]->daysUsed);
    }

    #[Test]
    public function deux_contrats_consecutifs_meme_vehicule_meme_entreprise_dans_le_meme_mois_dedoublent_le_jour_pivot(): void
    {
        // Contrat 1 : 1 → 10 mars. Contrat 2 : 11 → 20 mars. Aucun
        // chevauchement (les triggers DB l'interdiraient de toute façon),
        // total = 20 jours.
        $company = Company::factory()->create();
        $vehicle = Vehicle::factory()->create(['license_plate' => 'AA-001-AA']);
        $this->createPricing($vehicle, 2024, self::DAILY, self::WEEKLY, self::MONTHLY);

        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => '2024-03-01',
            'end_date' => '2024-03-10',
        ]);
        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => '2024-03-11',
            'end_date' => '2024-03-20',
        ]);

        $result = $this->calculator->calculate($company->id, 2024, 3);

        $this->assertCount(1, $result->lines, 'Une seule ligne par véhicule, même avec plusieurs contrats.');
        $this->assertSame(20, $result->lines[0]->daysUsed);
    }

    #[Test]
    public function un_vehicule_sans_tarif_leve_missing_pricing_exception(): void
    {
        $company = Company::factory()->create();
        $vehicle = Vehicle::factory()->create(['license_plate' => 'AA-001-AA']);
        // Pas de pricing volontairement.

        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => '2024-03-05',
            'end_date' => '2024-03-15',
        ]);

        try {
            $this->calculator->calculate($company->id, 2024, 3);
            $this->fail('Une MissingPricingException aurait dû être levée.');
        } catch (MissingPricingException $e) {
            $this->assertCount(1, $e->missing);
            $this->assertSame($vehicle->id, $e->missing[0]['vehicleId']);
            $this->assertSame('AA-001-AA', $e->missing[0]['licensePlate']);
            $this->assertSame(2024, $e->missing[0]['year']);
        }
    }

    #[Test]
    public function exception_collecte_tous_les_vehicules_manquants_en_un_seul_passage(): void
    {
        // Politique UX : le service ne stoppe pas au premier vehicule
        // sans tarif · il scanne tout et liste tous les manquants.
        $company = Company::factory()->create();
        $v1 = Vehicle::factory()->create(['license_plate' => 'AA-111-AA']);
        $v2 = Vehicle::factory()->create(['license_plate' => 'BB-222-BB']);
        $v3 = Vehicle::factory()->create(['license_plate' => 'CC-333-CC']);

        // v2 a un tarif, v1 et v3 non.
        $this->createPricing($v2, 2024, self::DAILY, self::WEEKLY, self::MONTHLY);

        Contract::factory()->forVehicle($v1)->forCompany($company)->create([
            'start_date' => '2024-03-01', 'end_date' => '2024-03-10',
        ]);
        Contract::factory()->forVehicle($v2)->forCompany($company)->create([
            'start_date' => '2024-03-01', 'end_date' => '2024-03-10',
        ]);
        Contract::factory()->forVehicle($v3)->forCompany($company)->create([
            'start_date' => '2024-03-01', 'end_date' => '2024-03-10',
        ]);

        try {
            $this->calculator->calculate($company->id, 2024, 3);
            $this->fail('Une MissingPricingException aurait dû être levée.');
        } catch (MissingPricingException $e) {
            $this->assertCount(2, $e->missing);
            $plates = array_column($e->missing, 'licensePlate');
            $this->assertContains('AA-111-AA', $plates);
            $this->assertContains('CC-333-CC', $plates);
        }
    }

    #[Test]
    public function le_tarif_de_l_annee_du_mois_facture_est_utilise_meme_si_le_contrat_deborde(): void
    {
        // Contrat 15 dec 2024 → 20 jan 2025. On facture janvier 2025 :
        // c'est le tarif 2025 qui doit s'appliquer (pas le tarif 2024).
        $company = Company::factory()->create();
        $vehicle = Vehicle::factory()->create(['license_plate' => 'AA-001-AA']);
        $this->createPricing($vehicle, 2024, dailyCents: 1_000, weeklyCents: 5_000, monthlyCents: 20_000);
        $this->createPricing($vehicle, 2025, dailyCents: 9_000, weeklyCents: 50_000, monthlyCents: 180_000);

        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => '2024-12-15',
            'end_date' => '2025-01-20',
        ]);

        $result = $this->calculator->calculate($company->id, 2025, 1);

        // 20 jours en janvier 2025. Tarif 2025 : 90/500/1800.
        // 20 jours optimaux : 2 sem (100 000) + 6 jours (54 000) = 154 000.
        // Vs 3 sem (150 000, couvre 21). Vs 1 mois (180 000). → 3 sem
        // (150 000) gagne car couvre 21 ≥ 20 et coûte 150 000 < 154 000.
        $this->assertSame(20, $result->lines[0]->daysUsed);
        $this->assertSame(0, $result->lines[0]->monthsBilled);
        $this->assertSame(3, $result->lines[0]->weeksBilled);
        $this->assertSame(0, $result->lines[0]->daysBilled);
        $this->assertSame(150_000, $result->lines[0]->totalCents);
    }

    #[Test]
    public function plusieurs_vehicules_sont_listes_tries_par_plaque(): void
    {
        $company = Company::factory()->create();
        $v1 = Vehicle::factory()->create(['license_plate' => 'ZZ-999-ZZ']);
        $v2 = Vehicle::factory()->create(['license_plate' => 'AA-111-AA']);

        $this->createPricing($v1, 2024, self::DAILY, self::WEEKLY, self::MONTHLY);
        $this->createPricing($v2, 2024, self::DAILY, self::WEEKLY, self::MONTHLY);

        Contract::factory()->forVehicle($v1)->forCompany($company)->create([
            'start_date' => '2024-03-01', 'end_date' => '2024-03-10',
        ]);
        Contract::factory()->forVehicle($v2)->forCompany($company)->create([
            'start_date' => '2024-03-01', 'end_date' => '2024-03-10',
        ]);

        $result = $this->calculator->calculate($company->id, 2024, 3);

        $this->assertCount(2, $result->lines);
        $this->assertSame('AA-111-AA', $result->lines[0]->licensePlate);
        $this->assertSame('ZZ-999-ZZ', $result->lines[1]->licensePlate);
    }

    #[Test]
    public function total_cents_correspond_a_la_somme_des_lignes(): void
    {
        $company = Company::factory()->create();
        $v1 = Vehicle::factory()->create(['license_plate' => 'AA-111-AA']);
        $v2 = Vehicle::factory()->create(['license_plate' => 'BB-222-BB']);

        $this->createPricing($v1, 2024, self::DAILY, self::WEEKLY, self::MONTHLY);
        $this->createPricing($v2, 2024, self::DAILY, self::WEEKLY, self::MONTHLY);

        // V1 : 7 jours → 1 sem = 50 000.
        Contract::factory()->forVehicle($v1)->forCompany($company)->create([
            'start_date' => '2024-03-01', 'end_date' => '2024-03-07',
        ]);
        // V2 : 30 jours → 1 mois = 180 000.
        Contract::factory()->forVehicle($v2)->forCompany($company)->create([
            'start_date' => '2024-03-01', 'end_date' => '2024-03-30',
        ]);

        $result = $this->calculator->calculate($company->id, 2024, 3);

        $sum = array_sum(array_map(static fn ($l): int => $l->totalCents, $result->lines));
        $this->assertSame($sum, $result->totalCents);
        $this->assertSame(230_000, $result->totalCents);
    }

    #[Test]
    public function ignore_les_contrats_d_autres_entreprises(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $vehicle = Vehicle::factory()->create(['license_plate' => 'AA-001-AA']);

        $this->createPricing($vehicle, 2024, self::DAILY, self::WEEKLY, self::MONTHLY);

        Contract::factory()->forVehicle($vehicle)->forCompany($companyA)->create([
            'start_date' => '2024-03-01', 'end_date' => '2024-03-10',
        ]);

        $resultB = $this->calculator->calculate($companyB->id, 2024, 3);

        $this->assertSame([], $resultB->lines);
        $this->assertSame(0, $resultB->totalCents);
    }

    #[Test]
    public function fevrier_28_jours_facture_un_mois_si_couverture_complete(): void
    {
        // Mois civil de 28 jours, contrat plein. 1 mois (couvre 30) =
        // 180 000. Vs 4 sem (200 000). → 1 mois gagne.
        $company = Company::factory()->create();
        $vehicle = Vehicle::factory()->create(['license_plate' => 'AA-001-AA']);
        $this->createPricing($vehicle, 2026, self::DAILY, self::WEEKLY, self::MONTHLY);

        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => '2026-02-01',
            'end_date' => '2026-02-28',
        ]);

        $result = $this->calculator->calculate($company->id, 2026, 2);

        $this->assertSame(28, $result->lines[0]->daysUsed);
        $this->assertSame(1, $result->lines[0]->monthsBilled);
        $this->assertSame(0, $result->lines[0]->weeksBilled);
        $this->assertSame(0, $result->lines[0]->daysBilled);
        $this->assertSame(180_000, $result->lines[0]->totalCents);
    }

    // ---------------------------------------------------------------
    // Scénarios CDC § 9.1 · Audit auto-documenté pour le client (T5).
    //
    // Tarifs : 20 € / 100 € / 350 € (cents : 2 000 / 10 000 / 35 000).
    // Ces 6 tests gravent dans la suite les comportements du brief CDC
    // (règle « tarif le plus avantageux », bissextile, indispos non
    // déduites, clip exit_date). Les invariants algorithmiques 0..31j
    // sont déjà couverts par OptimalRateBreakdownTest.
    // ---------------------------------------------------------------

    private const int CDC_DAILY = 2_000;

    private const int CDC_WEEKLY = 10_000;

    private const int CDC_MONTHLY = 35_000;

    #[Test]
    public function cdc_vingt_deux_jours_facture_trois_semaines_plus_un_jour(): void
    {
        // 22 j × 20 = 440, 3 sem × 100 + 1 j × 20 = 320, 1 mois = 350.
        // Optimal : 320 € (3 semaines + 1 jour).
        [$company, $vehicle] = $this->seedCdcVehicle();

        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => '2024-03-01',
            'end_date' => '2024-03-22',
        ]);

        $result = $this->calculator->calculate($company->id, 2024, 3);

        $this->assertSame(22, $result->lines[0]->daysUsed);
        $this->assertSame(0, $result->lines[0]->monthsBilled);
        $this->assertSame(3, $result->lines[0]->weeksBilled);
        $this->assertSame(1, $result->lines[0]->daysBilled);
        $this->assertSame(32_000, $result->totalCents);
    }

    #[Test]
    public function cdc_vingt_six_jours_facture_un_mois_plein_car_plus_avantageux(): void
    {
        // 26 j × 20 = 520, 3 sem + 5 j = 300 + 100 = 400, 1 mois = 350.
        // Optimal : 350 € (mois plein) · démontre la doctrine « tarif le
        // plus avantageux » même quand on n'atteint pas 30 j d'utilisation.
        [$company, $vehicle] = $this->seedCdcVehicle();

        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => '2024-03-01',
            'end_date' => '2024-03-26',
        ]);

        $result = $this->calculator->calculate($company->id, 2024, 3);

        $this->assertSame(26, $result->lines[0]->daysUsed);
        $this->assertSame(1, $result->lines[0]->monthsBilled);
        $this->assertSame(0, $result->lines[0]->weeksBilled);
        $this->assertSame(0, $result->lines[0]->daysBilled);
        $this->assertSame(35_000, $result->totalCents);
    }

    /**
     * @return array<string, array{0: int, 1: int, 2: int, 3: int}>
     */
    public static function huitATreizeJoursProvider(): array
    {
        // Combinaison optimale pour 8..13 jours avec tarifs CDC (20/100/350) :
        //   8j  → 1 sem + 1 j = 12 000  (vs 8×20=160, 2 sem=200)
        //   9j  → 1 sem + 2 j = 14 000
        //  10j  → 1 sem + 3 j = 16 000
        //  11j  → 1 sem + 4 j = 18 000
        //  12j  → 1 sem + 5 j = 20 000  (égal à 2 sem ; algo retient la
        //                                première combinaison en `<` strict)
        //  13j  → 2 sem        = 20 000  (pivot : 1 sem + 6 j = 220 € > 200 €)
        return [
            '8 jours = 1 sem + 1 j' => [8, 12_000, 1, 1],
            '9 jours = 1 sem + 2 j' => [9, 14_000, 1, 2],
            '10 jours = 1 sem + 3 j' => [10, 16_000, 1, 3],
            '11 jours = 1 sem + 4 j' => [11, 18_000, 1, 4],
            '12 jours = 1 sem + 5 j' => [12, 20_000, 1, 5],
            '13 jours = 2 sem (pivot)' => [13, 20_000, 2, 0],
        ];
    }

    #[Test]
    #[DataProvider('huitATreizeJoursProvider')]
    public function cdc_huit_a_treize_jours_facture_la_combinaison_optimale(
        int $endDay,
        int $expectedTotalCents,
        int $expectedWeeks,
        int $expectedDays,
    ): void {
        [$company, $vehicle] = $this->seedCdcVehicle();

        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => '2024-03-01',
            'end_date' => sprintf('2024-03-%02d', $endDay),
        ]);

        $result = $this->calculator->calculate($company->id, 2024, 3);

        $this->assertSame($endDay, $result->lines[0]->daysUsed);
        $this->assertSame($expectedWeeks, $result->lines[0]->weeksBilled);
        $this->assertSame($expectedDays, $result->lines[0]->daysBilled);
        $this->assertSame($expectedTotalCents, $result->totalCents);
    }

    #[Test]
    public function cdc_fevrier_2024_bissextile_facture_un_mois(): void
    {
        // 2024 est bissextile (29 jours en février). Le forfait mensuel
        // couvre 30 jours d'utilisation par convention, donc 29 j ≤ 1 mois.
        // 29 × 20 = 580, 4 sem + 1 j = 420, 1 mois = 350. Optimal : 350.
        [$company, $vehicle] = $this->seedCdcVehicle();

        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => '2024-02-01',
            'end_date' => '2024-02-29',
        ]);

        $result = $this->calculator->calculate($company->id, 2024, 2);

        $this->assertSame(29, $result->lines[0]->daysUsed);
        $this->assertSame(1, $result->lines[0]->monthsBilled);
        $this->assertSame(0, $result->lines[0]->weeksBilled);
        $this->assertSame(0, $result->lines[0]->daysBilled);
        $this->assertSame(35_000, $result->totalCents);
    }

    #[Test]
    public function cdc_indisponibilite_n_affecte_pas_le_calcul_de_facturation(): void
    {
        // Politique V1.2 (CDC § 9.1.2) : les indisponibilités ne sont pas
        // déduites du facturable. Garde-fou contre une régression future
        // qui introduirait silencieusement une déduction.
        [$company, $vehicle] = $this->seedCdcVehicle();

        // Contrat plein mois : 31 jours en mars. 31 j → 1 mois (35 000)
        // + 1 j (2 000) = 37 000. (Le mois unitaire couvre 30 j, le 31e
        // jour est facturé à l'unité · comportement déjà prouvé par les
        // tests OptimalRateBreakdown.)
        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => '2024-03-01',
            'end_date' => '2024-03-31',
        ]);

        // Indisponibilité de 5 jours au milieu du mois : ne doit pas
        // diminuer le facturable.
        Unavailability::factory()->for($vehicle)->maintenance()->create([
            'start_date' => '2024-03-10',
            'end_date' => '2024-03-14',
        ]);

        $result = $this->calculator->calculate($company->id, 2024, 3);

        // 31 jours utilisés (les 5 jours d'indispo restent comptés),
        // montant inchangé par rapport au scénario sans indispo.
        $this->assertSame(31, $result->lines[0]->daysUsed);
        $this->assertSame(1, $result->lines[0]->monthsBilled);
        $this->assertSame(0, $result->lines[0]->weeksBilled);
        $this->assertSame(1, $result->lines[0]->daysBilled);
        $this->assertSame(37_000, $result->totalCents);
    }

    #[Test]
    public function cdc_un_vehicule_sorti_clip_facturation_a_exit_date(): void
    {
        // ADR-0018 : un véhicule sorti définitivement (exit_date) n'est
        // plus facturable au-delà de cette date. Defense in depth : la
        // Validation Rule AvailableForPeriod bloque normalement la
        // création d'un contrat débordant exit_date, mais le calculator
        // protège aussi contre toute incohérence résiduelle.
        //
        // Véhicule sorti le 15 mars 2024 (vendu). Contrat couvre tout mars.
        // Facturation mars : 15 jours (du 1er au 15 inclus), pas 31.
        [$company, $vehicle] = $this->seedCdcVehicle();

        $vehicle->update([
            'exit_date' => '2024-03-15',
            'exit_reason' => VehicleExitReason::Sold,
        ]);

        Contract::factory()->forVehicle($vehicle)->forCompany($company)->create([
            'start_date' => '2024-03-01',
            'end_date' => '2024-03-31',
        ]);

        $result = $this->calculator->calculate($company->id, 2024, 3);

        // 15 jours optimaux : 2 sem + 1 j = 22 000 (vs 15 × 20 = 300,
        // 1 sem + 8 j = 260, 3 sem = 300). Optimal : 220 €.
        $this->assertSame(15, $result->lines[0]->daysUsed);
        $this->assertSame(0, $result->lines[0]->monthsBilled);
        $this->assertSame(2, $result->lines[0]->weeksBilled);
        $this->assertSame(1, $result->lines[0]->daysBilled);
        $this->assertSame(22_000, $result->totalCents);
    }

    /**
     * Setup partagé pour les scénarios CDC : Company + Vehicle + Pricing
     * 2024 aux tarifs CDC (20 € / 100 € / 350 €).
     *
     * @return array{0: Company, 1: Vehicle}
     */
    private function seedCdcVehicle(): array
    {
        $company = Company::factory()->create();
        $vehicle = Vehicle::factory()->create(['license_plate' => 'AA-001-AA']);
        $this->createPricing($vehicle, 2024, self::CDC_DAILY, self::CDC_WEEKLY, self::CDC_MONTHLY);

        return [$company, $vehicle];
    }

    private function createPricing(
        Vehicle $vehicle,
        int $year,
        int $dailyCents,
        int $weeklyCents,
        int $monthlyCents,
    ): VehicleYearlyPricing {
        return VehicleYearlyPricing::factory()
            ->for($vehicle)
            ->forYear($year)
            ->withRates($dailyCents, $weeklyCents, $monthlyCents)
            ->create();
    }
}
