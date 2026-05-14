<?php

declare(strict_types=1);

namespace Tests\Feature\Fiscal;

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
use App\Models\Company;
use App\Models\Contract;
use App\Models\Unavailability;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use App\Services\Fiscal\Declaration\DeclarationFiscalEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Suite **goldens BOFiP officiels** · centralise tous les exemples
 * chiffrés cités nominativement par l'administration fiscale (DGFiP)
 * dans les Bulletins Officiels des Finances Publiques (BOFiP) sur la
 * taxe annuelle sur les émissions de CO₂ et la taxe annuelle sur les
 * émissions de polluants atmosphériques (ex-TVS).
 *
 * **Pourquoi un fichier dédié** · les goldens BOFiP représentent un
 * niveau d'opposabilité **supérieur** aux goldens calculés à la main
 * tranche par tranche · si l'administration publie 144,64 € pour un
 * cas X, le moteur **doit** retourner 144,64 €, indépendamment de la
 * lecture qu'on fait du texte légal. Toute divergence ici signale
 * soit · une régression du moteur, soit · une lecture incorrecte du
 * BOFiP, soit · une modification ultérieure du BOFiP non encore
 * intégrée.
 *
 * **Sources actuellement couvertes** ·
 *  - `BOI-AIS-MOB-10-30-20-20240710` (BOFiP 2024 · doctrine en vigueur
 *     jusqu'au durcissement LF 2024)
 *  - `BOI-AIS-MOB-10-30-20-20250528` (BOFiP 2025 · doctrine post-LF
 *    2024, barèmes durcis au 01/01/2025) · accessible à
 *    https://bofip.impots.gouv.fr/bofip/13954-PGP.html/identifiant=BOI-AIS-MOB-10-30-20-20250528
 *
 * **Comment enrichir** · à chaque consultation du BOFiP par le
 * responsable fiscal, identifier les exemples chiffrés (montants en
 * euros, profils de véhicule, durées, prorata) et créer une nouvelle
 * méthode `bofip_<paragraphe>_<sujet>_donne_<montant>` qui verrouille
 * la valeur cible. Documenter en PHPDoc · paragraphe exact, version
 * du BOFiP, formule détaillée.
 *
 * **Exemples 2026/2027 du BOFiP** · le BOFiP § 230 ex2 publie
 * également des goldens pour 2026 (213 € plein, 52,52 € prorata 90j,
 * 160,48 € prorata 275j) et 2027 (232 € plein, 116 € avec 50 %
 * d'affectation exonérée). Ces exemples ne peuvent pas être joués
 * tant que `Year2026Boot` / `Year2027Boot` ne sont pas créés. À
 * réactiver dès qu'ils existent.
 *
 * **Exemples hors périmètre V1 de l'application** ·
 *  - BOFiP § 30-50 · minoration 15 000 € pour véhicules frais
 *    professionnels (non supporté V1).
 *  - BOFiP § 290 · polluants Cat 1 avec coefficient pondérateur 50 %
 *    sur frais kilométriques 30 000 km → 32,5 € (mécanisme frais
 *    pro non supporté V1).
 */
final class BofipGoldensTest extends TestCase
{
    use RefreshDatabase;

    private DeclarationFiscalEngine $engine;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = $this->app->make(DeclarationFiscalEngine::class);
        $this->company = Company::factory()->create([
            'short_code' => 'BOF',
            'legal_name' => 'BoFiP Goldens SARL',
        ]);
    }

    // ============================================================
    // BOFiP 2024 · BOI-AIS-MOB-10-30-20-20240710
    // ============================================================

    /**
     * **BOFiP `BOI-AIS-MOB-10-30-20-20240710` § 230, exemple 2.**
     *
     * Véhicule M1 essence Euro 6 WLTP 100 g/km, 306 jours d'affectation
     * (année 2024 bissextile) ·
     *  - tarif annuel plein WLTP 2024 = 173 €
     *    (calcul · (55-14)*1 + (63-55)*2 + (95-63)*3 + (100-95)*4
     *     = 41 + 16 + 96 + 20 = 173 €)
     *  - prorata 306/366
     *  - **CO₂ due = 173 × 306/366 = 144.6393… → 144.64 €** (cité par
     *    le BOFiP)
     *  - polluants Cat1 = 100 € × 306/366 = 83.6065… → **83.61 €**
     */
    #[Test]
    public function bofip_2024_230_ex2_wltp_100g_306j_donne_co2_144_64_polluants_83_61(): void
    {
        $vehicle = $this->makeVehicleWltp(2024, co2: 100, category: PollutantCategory::Category1);

        // 306 jours · 2024-01-01 → 2024-11-01 (305 j calendaires inclusifs)
        // Pour 306 j exact : 2024-03-01 → 2024-12-31 = 306 j inclusifs.
        $this->makeContract($vehicle, '2024-03-01', '2024-12-31', ContractType::Lld);

        $snapshot = $this->engine->compute($this->company->id, 2024);
        self::assertCount(1, $snapshot->contractBreakdown);
        // CO₂ + polluants additionnés au centime
        self::assertEqualsWithDelta(144.64, $snapshot->co2DueTotal, 0.01);
        self::assertEqualsWithDelta(83.61, $snapshot->pollutantsDueTotal, 0.01);
    }

    // ============================================================
    // BOFiP 2025 · BOI-AIS-MOB-10-30-20-20250528 (durcissement LF 2024)
    // ============================================================

    /**
     * **BOFiP `BOI-AIS-MOB-10-30-20-20250528` § 230 exemple 1.**
     *
     * Véhicule M1 essence Euro 6 WLTP 100 g/km, contrat full-year ·
     *  - tarif annuel plein WLTP 2025 durci = **193 €**
     *    (calcul · 9 × 0 + (50-9) × 1 + (58-50) × 2 + (90-58) × 3
     *     + (100-90) × 4 = 41 + 16 + 96 + 40 = 193 €)
     *  - polluants Cat1 = 100 €
     *  - **total = 293 €** (full-year)
     *
     * Texte BOFiP exact (§ 230) ·
     *   « en 2025, le tarif annuel est égal à 9 x 0 + (50-9) x 1
     *     + (58-50) x 2 + (90-58) x 3 + (100-90) x 4 = 193 € »
     */
    #[Test]
    public function bofip_2025_230_ex1_wltp_100g_full_year_donne_co2_193_polluants_100(): void
    {
        $vehicle = $this->makeVehicleWltp(2025, co2: 100, category: PollutantCategory::Category1);
        $this->makeContract($vehicle, '2025-01-01', '2025-12-31', ContractType::Lld);

        $snapshot = $this->engine->compute($this->company->id, 2025);
        self::assertCount(1, $snapshot->contractBreakdown);
        self::assertEqualsWithDelta(193.0, $snapshot->co2DueTotal, 0.01);
        self::assertEqualsWithDelta(100.0, $snapshot->pollutantsDueTotal, 0.01);
        self::assertEqualsWithDelta(293.0, $snapshot->totalDue, 0.01);
    }

    /**
     * **BOFiP `BOI-AIS-MOB-10-30-20-20250528` § 230 exemple 2 (suite
     * 2025).**
     *
     * Reprise de l'exemple 1 (WLTP 100 g/km, M1 essence Cat1) · « en
     * 2025, il a été immobilisé en fourrière pendant 25 jours. Le
     * véhicule n'a été affecté à l'activité économique de l'entreprise
     * A que pendant 340 jours sur 365. Le montant de la taxe annuelle
     * due par A est donc 193 × 340/365 = **179,78 €**. »
     *
     * Calcul détaillé · 193 × 340/365 = 65620/365 = 179,7808… → 179,78 €
     * (arrondi half-up commercial 2 décimales · R-2025-003).
     */
    #[Test]
    public function bofip_2025_230_ex2_wltp_100g_avec_25j_fourriere_donne_co2_179_78(): void
    {
        $vehicle = $this->makeVehicleWltp(2025, co2: 100, category: PollutantCategory::Category1);
        $this->makeContract($vehicle, '2025-01-01', '2025-12-31', ContractType::Lld);
        // Indispo fourrière publique 25 jours (cas BOFiP exact)
        Unavailability::factory()->poundPublic()->create([
            'vehicle_id' => $vehicle->id,
            'start_date' => '2025-06-01',
            'end_date' => '2025-06-25',
        ]);

        $snapshot = $this->engine->compute($this->company->id, 2025);
        self::assertEqualsWithDelta(179.78, $snapshot->co2DueTotal, 0.02);
        // Polluants suit le même prorata · 100 × 340/365 = 93,15 €
        self::assertEqualsWithDelta(93.15, $snapshot->pollutantsDueTotal, 0.02);
    }

    /**
     * **BOFiP `BOI-AIS-MOB-10-30-20-20250528` § 190.**
     *
     * « Cette exonération s'applique [...] lorsqu'elle couvre deux
     * mois civils successifs, dans la limite de trente jours
     * (exemple : un véhicule loué du 1er février au 2 mars). »
     *
     * Cas représentatif du BOFiP · contrat LCD à cheval sur 2 mois
     * civils (février = 28j en 2025 + 2 jours mars = 30 j total) →
     * exonéré, taxe = 0 €.
     */
    #[Test]
    public function bofip_2025_190_lcd_1er_fevrier_au_2_mars_30j_a_cheval_donne_zero(): void
    {
        $vehicle = $this->makeVehicleWltp(2025, co2: 100, category: PollutantCategory::Category1);
        // 28 j (février 2025 non bissextile) + 2 j (1-2 mars) = 30 j
        $this->makeContract($vehicle, '2025-02-01', '2025-03-02', ContractType::Lcd);

        $snapshot = $this->engine->compute($this->company->id, 2025);
        self::assertSame(0.0, $snapshot->totalDue);
    }

    /**
     * **BOFiP `BOI-AIS-MOB-10-30-10-20250528` § 190 exemple 1
     * (acquisition mid-year).**
     *
     * Texte BOFiP exact ·
     *   « Si une entreprise acquiert un véhicule au 31 janvier 2022 et
     *     le revend au 30 novembre de la même année, la proportion
     *     annuelle d'affectation de ce véhicule à cette entreprise sera
     *     égale à 83,3 % : 304 jours (nombre de jours de l'année
     *     écoulés entre le 1er février et le 30 novembre) / 365. »
     *
     * Adapté à 2025 (non bissextile, dénominateur 365 identique) ·
     * véhicule M1 WLTP 100g Cat1, contrat 01/02/2025 → 30/11/2025
     * (304 jours).
     *  - CO₂ = 193 × 304/365 = 160,75 €
     *  - polluants = 100 × 304/365 = 83,29 €
     *  - total ≈ 244,04 €
     */
    #[Test]
    public function bofip_2025_30_10_190_ex1_acquisition_mid_year_304j_donne_proportion_83_3(): void
    {
        // Le BOFiP affiche 304/365 = 83,3 % en partant de « acquisition
        // 31/01 → cession 30/11 » (le BOFiP compte le 31/01 comme jour
        // 1 d'affectation, le 30/11 inclus). Cf. note pédagogique
        // explicative qui mentionne « entre le 1er février et le 30
        // novembre » = 303 j · le BOFiP retient 304 j en incluant le
        // jour d'acquisition. Floty matche en posant contract du
        // 2025-01-31 au 2025-11-30 (inclusif·inclusif = 304 j).
        $vehicle = $this->makeVehicleWltp(2025, co2: 100, category: PollutantCategory::Category1);
        $this->makeContract($vehicle, '2025-01-31', '2025-11-30', ContractType::Lld);

        $snapshot = $this->engine->compute($this->company->id, 2025);
        // 293 × 304/365 = 244,038… ≈ 244,04 €
        $expected = 293.0 * 304 / 365;
        self::assertEqualsWithDelta($expected, $snapshot->totalDue, 0.05);
    }

    /**
     * **BOFiP `BOI-AIS-MOB-10-30-10-20250528` § 190 exemple 2
     * (fourrière publique 15 jours).**
     *
     * Texte BOFiP exact ·
     *   « Une entreprise détient un véhicule tout au long de l'année
     *     2022 [...]. À la suite d'une infraction routière, il est mis
     *     en fourrière pendant 15 jours. La proportion annuelle
     *     d'affectation de cette durée sera de 95,9 % : 350 / 365. »
     *
     * Adapté à 2025 · véhicule M1 WLTP 100g Cat1, contrat full-year +
     * indispo fourrière publique 15 j (R-2025-008).
     *  - prorata effectif = 350/365 = 95,9 %
     *  - CO₂ = 193 × 350/365 ≈ 185,07 €
     *  - polluants = 100 × 350/365 ≈ 95,89 €
     *  - total ≈ 280,96 €
     */
    #[Test]
    public function bofip_2025_30_10_190_ex2_fourriere_publique_15j_donne_proportion_95_9(): void
    {
        $vehicle = $this->makeVehicleWltp(2025, co2: 100, category: PollutantCategory::Category1);
        $this->makeContract($vehicle, '2025-01-01', '2025-12-31', ContractType::Lld);
        // Fourrière publique 15 jours (cas BOFiP exact)
        Unavailability::factory()->poundPublic()->create([
            'vehicle_id' => $vehicle->id,
            'start_date' => '2025-05-01',
            'end_date' => '2025-05-15',
        ]);

        $snapshot = $this->engine->compute($this->company->id, 2025);
        // 293 × 350/365 = 280,958… ≈ 280,96 €
        $expected = 293.0 * 350 / 365;
        self::assertEqualsWithDelta($expected, $snapshot->totalDue, 0.05);
    }

    /**
     * Dérivation directe de l'exemple précédent · même véhicule WLTP
     * 100 g/km **avec abattement E85** (CIBS L. 421-125 réformé,
     * applicable au 01/01/2025 par LF 2024 art. 97, 23°).
     *
     * Calcul ·
     *  - CO₂ retenu = round(100 × 0.60) = 60 g/km
     *  - tarif barème 2025 à 60 g/km · (50-9)*1 + (58-50)*2 + (60-58)*3
     *    = 41 + 16 + 6 = **63 €** CO₂
     *  - polluants Cat1 inchangés = 100 €
     *  - total = 163 €
     *
     * **Source** · CIBS L. 421-125 publié, barème CIBS L. 421-120
     * durci par LF 2024 art. 97, 19°. Cas explicitement prévu par
     * la réforme E85 2025.
     */
    #[Test]
    public function bofip_2025_e85_wltp_100g_abattu_donne_co2_63_polluants_100(): void
    {
        $vehicle = $this->makeVehicleWltp(
            2025,
            co2: 100,
            category: PollutantCategory::Category1,
            acceptsE85: true,
        );
        $this->makeContract($vehicle, '2025-01-01', '2025-12-31', ContractType::Lld);

        $snapshot = $this->engine->compute($this->company->id, 2025);
        self::assertEqualsWithDelta(63.0, $snapshot->co2DueTotal, 0.01);
        self::assertEqualsWithDelta(100.0, $snapshot->pollutantsDueTotal, 0.01);
        self::assertEqualsWithDelta(163.0, $snapshot->totalDue, 0.01);
    }

    /**
     * Référence directe BOFiP polluants · catégories E (électrique /
     * hydrogène) = 0 € sur les 2 taxes.
     *
     * CIBS L. 421-128 (CO₂) + L. 421-140 (polluants) · texte stable
     * 31/12/2023 → 01/03/2026 (revalorisation LF 2026 art. 58 reportée
     * à mars 2026).
     */
    #[Test]
    public function bofip_2025_polluants_electrique_donne_zero(): void
    {
        $vehicle = $this->makeVehicleWltp(
            2025,
            co2: 0,
            category: PollutantCategory::E,
            energySource: EnergySource::Electric,
        );
        $this->makeContract($vehicle, '2025-01-01', '2025-12-31', ContractType::Lld);

        $snapshot = $this->engine->compute($this->company->id, 2025);
        self::assertSame(0.0, $snapshot->totalDue);
    }

    /**
     * Référence directe BOFiP polluants · catégorie « plus polluants »
     * (Euro < 5 ou avant Euro) = **500 €/an** (tarif plein).
     *
     * CIBS L. 421-135 · valeurs publiées par décret.
     */
    #[Test]
    public function bofip_2025_polluants_les_plus_polluants_diesel_donne_500(): void
    {
        // Diesel Euro 6 → catégorie « plus polluants » via R-2025-013
        // (cf. PollutantCategory::derive). co2_wltp = 0 isole le tarif
        // polluants (CO₂ tranche 0-9 = 0 €).
        $vehicle = $this->makeVehicleWltp(
            2025,
            co2: 0,
            category: PollutantCategory::MostPolluting,
            energySource: EnergySource::Diesel,
        );
        $this->makeContract($vehicle, '2025-01-01', '2025-12-31', ContractType::Lld);

        $snapshot = $this->engine->compute($this->company->id, 2025);
        self::assertEqualsWithDelta(500.0, $snapshot->pollutantsDueTotal, 0.01);
    }

    /**
     * BOFiP § 180-190 · qualification LCD (location courte durée) ·
     * contrat ≤ 30 jours OU contrat couvrant exactement un mois civil
     * entier · tous les jours exonérés des 2 taxes.
     *
     * Test miroir 2024 / 2025 pour confirmer la reconduction stricte
     * de la doctrine.
     */
    #[Test]
    public function bofip_lcd_30j_strict_donne_zero_2025(): void
    {
        $vehicle = $this->makeVehicleWltp(2025, co2: 100, category: PollutantCategory::Category1);
        $this->makeContract($vehicle, '2025-04-01', '2025-04-30', ContractType::Lcd); // 30 j

        $snapshot = $this->engine->compute($this->company->id, 2025);
        self::assertSame(0.0, $snapshot->totalDue);
    }

    // ============================================================
    // Helpers
    // ============================================================

    private function makeVehicleWltp(
        int $year,
        int $co2,
        PollutantCategory $category,
        EnergySource $energySource = EnergySource::Gasoline,
        bool $acceptsE85 = false,
    ): Vehicle {
        $vehicle = Vehicle::create([
            'license_plate' => sprintf('B%d-%03d-B%d', random_int(1, 9), random_int(1, 999), random_int(1, 9)),
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
            'effective_from' => Carbon::parse(sprintf('%d-01-01', $year)),
            'effective_to' => null,
            'reception_category' => ReceptionCategory::M1,
            'vehicle_user_type' => VehicleUserType::PassengerCar,
            'body_type' => BodyType::InteriorDriving,
            'seats_count' => 5,
            'energy_source' => $energySource,
            'euro_standard' => EuroStandard::Euro6,
            'pollutant_category' => $category,
            'homologation_method' => HomologationMethod::Wltp,
            'co2_wltp' => $co2,
            'taxable_horsepower' => 6,
            'handicap_access' => false,
            'accepts_e85' => $acceptsE85,
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
