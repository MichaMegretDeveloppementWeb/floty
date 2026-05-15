<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Company\CompanyColor;
use App\Enums\Contract\ContractType;
use App\Enums\Unavailability\UnavailabilityType;
use App\Enums\Vehicle\BodyType;
use App\Enums\Vehicle\EnergySource;
use App\Enums\Vehicle\EuroStandard;
use App\Enums\Vehicle\FiscalCharacteristicsChangeReason;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\PollutantCategory;
use App\Enums\Vehicle\ReceptionCategory;
use App\Enums\Vehicle\UnderlyingCombustionEngineType;
use App\Enums\Vehicle\VehicleExitReason;
use App\Enums\Vehicle\VehicleStatus;
use App\Enums\Vehicle\VehicleUserType;
use App\Models\BillingSettings;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Driver;
use App\Models\Unavailability;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use App\Models\VehicleYearlyPricing;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Seeder de démo pour peupler l'année fiscale 2024.
 *
 * À lancer ponctuellement pour une démo client :
 *     php artisan db:seed --class=DemoSeeder
 *
 * Produit :
 *   - 5 entreprises avec 5 couleurs distinctes du design system
 *   - 10 véhicules (mix VP/VU, WLTP/NEDC/PA, Euro 5+/autres, CO₂ variés)
 *   - Une vraie diversité de situations fiscales pour démontrer le moteur :
 *     électrique exonéré, Diesel Euro 6 « plus polluants », essence
 *     Euro 6 WLTP taxable, ancien véhicule PA, hybride essence, handicap,
 *     etc.
 *   - ~200 attributions sur 2024 étalées de façon à produire plusieurs
 *     couples (véhicule, entreprise) sous ET au-dessus du seuil LCD 30 j.
 */
final class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // Pas de DB::transaction globale · le seedContracts tolère les
        // overlaps individuellement (try-catch par contrat). Une
        // exception au milieu d'une transaction MySQL annulerait toutes
        // les insertions précédentes en silence.
        $this->seedBillingSettings();
        $companies = $this->seedCompanies();
        $vehicles = $this->seedVehicles();
        $this->seedPricings($vehicles);
        $this->seedContracts($vehicles, $companies);
        $this->seedUnavailabilities($vehicles);
        $this->seedDrivers($companies);
    }

    /**
     * Émetteur de facture par défaut (Phase 14 facturation).
     * `Sogema Rent` est l'entité juridique qui édite les factures :
     * Floty n'est que l'outil. Singleton applicatif pré-rempli pour
     * que la première facture émise soit déjà cohérente sans passage
     * obligé par la page Paramètres.
     */
    private function seedBillingSettings(): void
    {
        $settings = BillingSettings::singleton();
        $settings->fill([
            'name' => 'Sogema Rent',
            'address_line_1' => 'Vessac',
            'address_line_2' => null,
            'postal_code' => '12720',
            'city' => 'Saint-André-de-Vézines',
            'siren' => null,
            'contact_email' => null,
        ]);
        $settings->save();
    }

    /**
     * Tarifs jour/semaine/mois par véhicule × année (Phase 14 facturation).
     * Calibrés par catégorie pour produire une démo crédible :
     *   - Citadine standard : 70 €/j
     *   - Premium : 140-150 €/j
     *   - Utilitaire : 80-100 €/j
     *   - Économique/vintage : 45-50 €/j
     * Triplet cohérent : S ≈ 5.7 × J et M ≈ 20 × J (encourage le moteur
     * `OptimalRateBreakdown` à mixer mois + semaines + jours).
     *
     * Couvre 2024 → année courante avec +5 %/an arrondi à l'euro.
     * Les véhicules sortis ne reçoivent pas de tarif pour les années
     * postérieures à la sortie.
     *
     * @param  array<string, Vehicle>  $vehicles
     */
    private function seedPricings(array $vehicles): void
    {
        $baseRates2024 = [
            'EA-001-AA' => [70, 400, 1400],
            'EB-002-BB' => [95, 550, 1900],
            'EC-003-CC' => [140, 800, 2800],
            'ED-004-DD' => [50, 280, 950],
            'EE-005-EE' => [45, 250, 850],
            'EF-006-FF' => [65, 370, 1300],
            'EG-007-GG' => [150, 870, 3000],
            'EH-008-HH' => [80, 460, 1600],
            'EI-009-II' => [100, 580, 2000],
            'EJ-010-JJ' => [75, 430, 1500],
            'EK-011-KK' => [60, 340, 1200],
            'EL-012-LL' => [75, 430, 1500],
        ];

        $currentYear = Carbon::now()->year;
        $years = range(2024, max($currentYear, 2026));

        foreach ($vehicles as $plate => $vehicle) {
            // Tarifs explicites pour les 12 premiers · fallback générique
            // pour les ~52 nouveaux (calculés depuis l'énergie et la kerb_mass).
            [$daily2024, $weekly2024, $monthly2024] = $baseRates2024[$plate] ?? $this->estimateFallbackRates($vehicle);

            foreach ($years as $year) {
                if ($vehicle->exit_date !== null && $year > $vehicle->exit_date->year) {
                    continue;
                }

                $multiplier = 1 + 0.05 * ($year - 2024);
                $daily = (int) round($daily2024 * $multiplier);
                $weekly = (int) round($weekly2024 * $multiplier);
                $monthly = (int) round($monthly2024 * $multiplier);

                VehicleYearlyPricing::updateOrCreate(
                    ['vehicle_id' => $vehicle->id, 'year' => $year],
                    [
                        'daily_rate_cents' => $daily * 100,
                        'weekly_rate_cents' => $weekly * 100,
                        'monthly_rate_cents' => $monthly * 100,
                    ],
                );
            }
        }
    }

    /**
     * Estimation grossière des tarifs J/S/M pour un véhicule sans spec
     * explicite · sert uniquement à éviter les véhicules sans pricing
     * en démo. Triplet cohérent S ≈ 5.7×J et M ≈ 20×J.
     *
     * @return array{0:int,1:int,2:int} [daily, weekly, monthly]
     */
    private function estimateFallbackRates(Vehicle $vehicle): array
    {
        $vfc = $vehicle->fiscalCharacteristics()->whereNull('effective_to')->first();
        $kerb = $vfc?->kerb_mass ?? 1300;
        $isElectric = in_array($vfc?->energy_source?->value, ['electric', 'hydrogen', 'electric_hydrogen'], true);

        // Base par poids · 50 € à 1000 kg, +5 €/100 kg
        $daily = (int) round(50 + ($kerb - 1000) / 20);
        // Premium électrique +30 %
        if ($isElectric) {
            $daily = (int) round($daily * 1.30);
        }
        $weekly = (int) round($daily * 5.7);
        $monthly = (int) round($daily * 20);

        return [$daily, $weekly, $monthly];
    }

    /**
     * 32 conducteurs démo couvrant tous les cas Driver↔Company N:N ·
     *  - 15 actifs · 1 par entreprise, lien actif depuis 2024 (cas standard)
     *  - 5 historiques · lien fermé entre 2024-2025 ou 2025-2026
     *  - 3 multi-companies · plusieurs liens successifs ou parallèles
     *  - 5 récents · création + lien 2025-2026
     *  - 2 sans lien actif · drivers orphelins (cas d'audit RH)
     *  - 2 multi-liens parallèles · 2 entreprises simultanément (cas
     *    mandataire social ou prestataire partagé)
     *
     * @param  array<string, Company>  $companies
     */
    private function seedDrivers(array $companies): void
    {
        $specs = [
            // === 15 actifs · 1 par entreprise ===
            ['first' => 'Marc', 'last' => 'Dubois', 'memberships' => [['code' => 'ACM', 'joined' => '2024-01-01', 'left' => null]]],
            ['first' => 'Thomas', 'last' => 'Petit', 'memberships' => [['code' => 'BTP', 'joined' => '2024-01-10', 'left' => null]]],
            ['first' => 'Julien', 'last' => 'Garnier', 'memberships' => [['code' => 'BAT', 'joined' => '2024-02-01', 'left' => null]]],
            ['first' => 'Olivier', 'last' => 'Roche', 'memberships' => [['code' => 'COB', 'joined' => '2024-03-15', 'left' => null]]],
            ['first' => 'Camille', 'last' => 'Roux', 'memberships' => [['code' => 'DRS', 'joined' => '2024-04-01', 'left' => null]]],
            ['first' => 'Élodie', 'last' => 'Lemaire', 'memberships' => [['code' => 'EOL', 'joined' => '2024-02-20', 'left' => null]]],
            ['first' => 'Mathieu', 'last' => 'Faure', 'memberships' => [['code' => 'HEX', 'joined' => '2024-01-08', 'left' => null]]],
            ['first' => 'Sylvain', 'last' => 'Vidal', 'memberships' => [['code' => 'IDF', 'joined' => '2024-05-01', 'left' => null]]],
            ['first' => 'Nicolas', 'last' => 'Moreau', 'memberships' => [['code' => 'ECO', 'joined' => '2024-01-20', 'left' => null]]],
            ['first' => 'Clément', 'last' => 'Henry', 'memberships' => [['code' => 'LOG', 'joined' => '2024-03-04', 'left' => null]]],
            ['first' => 'Vincent', 'last' => 'Picard', 'memberships' => [['code' => 'MAG', 'joined' => '2024-02-12', 'left' => null]]],
            ['first' => 'Romain', 'last' => 'Marchand', 'memberships' => [['code' => 'NOV', 'joined' => '2024-04-15', 'left' => null]]],
            ['first' => 'Julie', 'last' => 'Bernard', 'memberships' => [['code' => 'COR', 'joined' => '2024-02-01', 'left' => null]]],
            ['first' => 'Stéphanie', 'last' => 'Robin', 'memberships' => [['code' => 'PRO', 'joined' => '2024-03-20', 'left' => null]]],
            ['first' => 'Patrick', 'last' => 'Caron', 'memberships' => [['code' => 'TUR', 'joined' => '2024-01-15', 'left' => null]]],

            // === 5 historiques · lien fermé (effective_to non null) ===
            ['first' => 'Pierre', 'last' => 'Lefebvre', 'memberships' => [['code' => 'ACM', 'joined' => '2024-01-15', 'left' => '2025-08-31']]],
            ['first' => 'Laetitia', 'last' => 'Blanc', 'memberships' => [['code' => 'BTP', 'joined' => '2024-02-01', 'left' => '2025-12-31']]],
            ['first' => 'Sébastien', 'last' => 'Fontaine', 'memberships' => [['code' => 'ECO', 'joined' => '2024-03-01', 'left' => '2024-12-31']]],
            ['first' => 'Carine', 'last' => 'Leclerc', 'memberships' => [['code' => 'COR', 'joined' => '2024-04-01', 'left' => '2026-02-28']]],
            ['first' => 'Frédéric', 'last' => 'Lambert', 'memberships' => [['code' => 'DRS', 'joined' => '2024-06-15', 'left' => '2025-09-30']]],

            // === 3 multi-companies · liens successifs ou parallèles ===
            ['first' => 'Sophie', 'last' => 'Martin', 'memberships' => [
                ['code' => 'ACM', 'joined' => '2024-03-15', 'left' => '2025-06-30'],
                ['code' => 'BTP', 'joined' => '2025-07-01', 'left' => null],
            ]],
            ['first' => 'Aurélie', 'last' => 'Simon', 'memberships' => [
                ['code' => 'ECO', 'joined' => '2024-06-01', 'left' => null],
                ['code' => 'DRS', 'joined' => '2025-03-01', 'left' => null],
            ]],
            ['first' => 'David', 'last' => 'Girard', 'memberships' => [
                ['code' => 'LOG', 'joined' => '2024-01-01', 'left' => '2024-12-31'],
                ['code' => 'MAG', 'joined' => '2025-01-01', 'left' => '2025-12-31'],
                ['code' => 'NOV', 'joined' => '2026-01-01', 'left' => null],
            ]],

            // === 5 récents · création 2025-2026 ===
            ['first' => 'Manon', 'last' => 'Renaud', 'memberships' => [['code' => 'IDF', 'joined' => '2025-09-01', 'left' => null]]],
            ['first' => 'Hugo', 'last' => 'Charpentier', 'memberships' => [['code' => 'HEX', 'joined' => '2025-11-15', 'left' => null]]],
            ['first' => 'Sarah', 'last' => 'Joly', 'memberships' => [['code' => 'EOL', 'joined' => '2026-01-15', 'left' => null]]],
            ['first' => 'Lucas', 'last' => 'Carpentier', 'memberships' => [['code' => 'PRO', 'joined' => '2026-02-01', 'left' => null]]],
            ['first' => 'Inès', 'last' => 'Schmitt', 'memberships' => [['code' => 'NOV', 'joined' => '2026-03-15', 'left' => null]]],

            // === 2 sans lien actif · orphelins ===
            ['first' => 'Bernard', 'last' => 'Delacroix', 'memberships' => [['code' => 'BAT', 'joined' => '2024-01-01', 'left' => '2024-06-30']]],
            ['first' => 'Yvonne', 'last' => 'Lefèvre', 'memberships' => [['code' => 'COB', 'joined' => '2024-05-01', 'left' => '2024-11-30']]],

            // === 2 multi-liens parallèles · 2 entreprises simultanément ===
            ['first' => 'Christophe', 'last' => 'Dumas', 'memberships' => [
                ['code' => 'ACM', 'joined' => '2024-01-01', 'left' => null],
                ['code' => 'HEX', 'joined' => '2025-01-01', 'left' => null],
            ]],
            ['first' => 'Valérie', 'last' => 'Guillot', 'memberships' => [
                ['code' => 'COR', 'joined' => '2024-04-01', 'left' => null],
                ['code' => 'TUR', 'joined' => '2024-04-01', 'left' => null],
            ]],
        ];

        foreach ($specs as $spec) {
            $existing = Driver::query()
                ->where('first_name', $spec['first'])
                ->where('last_name', $spec['last'])
                ->first();
            if ($existing !== null) {
                continue;
            }

            $driver = Driver::create([
                'first_name' => $spec['first'],
                'last_name' => $spec['last'],
            ]);

            foreach ($spec['memberships'] as $m) {
                $company = $companies[$m['code']] ?? null;
                if ($company === null) {
                    continue;
                }
                DB::table('driver_company')->insert([
                    'driver_id' => $driver->id,
                    'company_id' => $company->id,
                    'joined_at' => $m['joined'],
                    'left_at' => $m['left'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * 15 entreprises sectorielles cohérentes · BTP (4), Services/Conseil (4),
     * Distribution/Logistique (4), Événementiel/Tourisme (3) ·
     *   - IDF Consulting Paris pour tester la majoration carte grise IDF
     *     +13 € de LF 2026 art. 60 (création L. 421-54-1)
     *   - 8 couleurs CompanyColor réutilisées (7 fois indigo + 8 autres = OK,
     *     la couleur n'a pas d'unicité métier)
     *   - SIREN fictifs 9 chiffres séquentiels (validation format uniquement)
     *
     * @return array<string, Company>
     */
    private function seedCompanies(): array
    {
        $specs = [
            // BTP / Construction (4)
            ['code' => 'ACM', 'name' => 'ACME Logistique', 'siren' => '812345678', 'color' => CompanyColor::Indigo, 'city' => 'Lyon'],
            ['code' => 'BTP', 'name' => 'BTP Confort', 'siren' => '813456789', 'color' => CompanyColor::Amber, 'city' => 'Grenoble'],
            ['code' => 'BAT', 'name' => 'Bâti Express', 'siren' => '817890123', 'color' => CompanyColor::Teal, 'city' => 'Saint-Étienne'],
            ['code' => 'COB', 'name' => 'Constructions Bossard', 'siren' => '818901234', 'color' => CompanyColor::Orange, 'city' => 'Marseille'],
            // Services / Conseil (4)
            ['code' => 'DRS', 'name' => 'Dauphiné Services', 'siren' => '815678901', 'color' => CompanyColor::Rose, 'city' => 'Valence'],
            ['code' => 'EOL', 'name' => 'Eole Conseils', 'siren' => '819012345', 'color' => CompanyColor::Cyan, 'city' => 'Toulouse'],
            ['code' => 'HEX', 'name' => 'Hexagone Solutions', 'siren' => '820123456', 'color' => CompanyColor::Indigo, 'city' => 'Bordeaux'],
            ['code' => 'IDF', 'name' => 'IDF Consulting', 'siren' => '821234567', 'color' => CompanyColor::Amber, 'city' => 'Paris'],
            // Distribution / Logistique (4)
            ['code' => 'ECO', 'name' => 'Éco Distribution', 'siren' => '816789012', 'color' => CompanyColor::Violet, 'city' => 'Lille'],
            ['code' => 'LOG', 'name' => 'LogiPro Transport', 'siren' => '822345678', 'color' => CompanyColor::Emerald, 'city' => 'Nantes'],
            ['code' => 'MAG', 'name' => 'MagaLogistic', 'siren' => '823456789', 'color' => CompanyColor::Rose, 'city' => 'Strasbourg'],
            ['code' => 'NOV', 'name' => 'Novexpress', 'siren' => '824567890', 'color' => CompanyColor::Violet, 'city' => 'Nice'],
            // Événementiel / Tourisme (3)
            ['code' => 'COR', 'name' => 'Corsica Events', 'siren' => '814567890', 'color' => CompanyColor::Emerald, 'city' => 'Ajaccio'],
            ['code' => 'PRO', 'name' => 'ProSpektacle', 'siren' => '825678901', 'color' => CompanyColor::Teal, 'city' => 'Reims'],
            ['code' => 'TUR', 'name' => 'Tourisme Atlantique', 'siren' => '826789012', 'color' => CompanyColor::Orange, 'city' => 'La Rochelle'],
        ];

        $created = [];
        foreach ($specs as $spec) {
            $created[$spec['code']] = Company::updateOrCreate(
                ['short_code' => $spec['code']],
                [
                    'legal_name' => $spec['name'],
                    'siren' => $spec['siren'],
                    'city' => $spec['city'],
                    'postal_code' => $this->postalFor($spec['city']),
                    'country' => 'FR',
                    'color' => $spec['color'],
                    'is_active' => true,
                ],
            );
        }

        return $created;
    }

    private function postalFor(string $city): string
    {
        return match ($city) {
            'Lyon' => '69003',
            'Grenoble' => '38000',
            'Saint-Étienne' => '42000',
            'Marseille' => '13001',
            'Valence' => '26000',
            'Toulouse' => '31000',
            'Bordeaux' => '33000',
            'Paris' => '75008',
            'Lille' => '59000',
            'Nantes' => '44000',
            'Strasbourg' => '67000',
            'Nice' => '06000',
            'Ajaccio' => '20000',
            'Reims' => '51100',
            'La Rochelle' => '17000',
            default => '75001',
        };
    }

    /**
     * @return array<string, Vehicle>
     */
    private function seedVehicles(): array
    {
        $specs = [
            // Peugeot 308 essence Euro 6 WLTP 100 g/km - exemple officiel BOFiP
            [
                'plate' => 'EA-001-AA', 'brand' => 'Peugeot', 'model' => '308',
                'regFrench' => '2022-06-15', 'regOrigin' => '2022-06-15', 'econ' => '2022-06-15',
                'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5,
                'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro6,
                'pollutant' => PollutantCategory::Category1,
                'method' => HomologationMethod::Wltp, 'co2Wltp' => 100, 'pa' => 6, 'kerb' => 1340,
            ],
            // Renault Trafic Diesel Euro 6 - « plus polluants »
            [
                'plate' => 'EB-002-BB', 'brand' => 'Renault', 'model' => 'Trafic',
                'regFrench' => '2021-04-10', 'regOrigin' => '2021-04-10', 'econ' => '2021-04-10',
                'user' => VehicleUserType::CommercialVehicle, 'body' => BodyType::LightTruck, 'cat' => ReceptionCategory::N1, 'seats' => 6,
                'energy' => EnergySource::Diesel, 'euro' => EuroStandard::Euro6,
                'pollutant' => PollutantCategory::MostPolluting,
                'method' => HomologationMethod::Wltp, 'co2Wltp' => 165, 'pa' => 8, 'kerb' => 1950,
                'n1PassengerTransport' => true,
            ],
            // Tesla Model 3 électrique - catégorie E, exonération CO₂
            [
                'plate' => 'EC-003-CC', 'brand' => 'Tesla', 'model' => 'Model 3',
                'regFrench' => '2023-02-14', 'regOrigin' => '2023-02-14', 'econ' => '2023-02-14',
                'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5,
                'energy' => EnergySource::Electric, 'euro' => null,
                'pollutant' => PollutantCategory::E,
                'method' => HomologationMethod::Wltp, 'co2Wltp' => 0, 'pa' => 9, 'kerb' => 1844,
            ],
            // Peugeot 207 essence NEDC 130 g/km - vieille immat. 2010
            [
                'plate' => 'ED-004-DD', 'brand' => 'Peugeot', 'model' => '207',
                'regFrench' => '2010-06-15', 'regOrigin' => '2010-06-15', 'econ' => '2010-06-15',
                'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5,
                'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro4,
                'pollutant' => PollutantCategory::MostPolluting, // essence pré-Euro 5 → most_polluting
                'method' => HomologationMethod::Nedc, 'co2Nedc' => 130, 'pa' => 5, 'kerb' => 1150,
            ],
            // Renault 21 essence 7 CV - PA (trop vieux pour NEDC).
            // Cas multi-VFC riche (chantier E) : 4 VFC au total dont 2 en
            // 2024 — exerce le segmenteur fiscal sur le calcul PA.
            // Bascules : 8 PA (2020-01-01) → 7 PA (2024-03-15) → 8 PA
            // (2024-09-10) — la 1ʳᵉ courante reste à 7 PA (initiale 2002).
            [
                'plate' => 'EE-005-EE', 'brand' => 'Renault', 'model' => '21 Nevada',
                'regFrench' => '2002-05-15', 'regOrigin' => '2002-05-15', 'econ' => '2002-05-15',
                'user' => VehicleUserType::PassengerCar, 'body' => BodyType::StationWagon, 'cat' => ReceptionCategory::M1, 'seats' => 5,
                'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro3,
                'pollutant' => PollutantCategory::MostPolluting,
                'method' => HomologationMethod::Pa, 'pa' => 7, 'kerb' => 1200,
                'extraVfcs' => [
                    ['from' => '2020-01-01', 'pa' => 8],
                    ['from' => '2024-03-15', 'pa' => 7],
                    ['from' => '2024-09-10', 'pa' => 8],
                ],
            ],
            // Toyota Yaris hybride essence Euro 6 WLTP 95 g/km
            [
                'plate' => 'EF-006-FF', 'brand' => 'Toyota', 'model' => 'Yaris Hybrid',
                'regFrench' => '2023-09-01', 'regOrigin' => '2023-09-01', 'econ' => '2023-09-01',
                'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5,
                'energy' => EnergySource::NonPluginHybrid, 'underlying' => UnderlyingCombustionEngineType::Gasoline,
                'euro' => EuroStandard::Euro6d,
                'pollutant' => PollutantCategory::Category1, // hybride essence → cat 1
                'method' => HomologationMethod::Wltp, 'co2Wltp' => 95, 'pa' => 5, 'kerb' => 1180,
            ],
            // BMW Série 5 Diesel Euro 6 - « plus polluants » gros CO₂
            [
                'plate' => 'EG-007-GG', 'brand' => 'BMW', 'model' => 'Série 5 520d',
                'regFrench' => '2022-11-20', 'regOrigin' => '2022-11-20', 'econ' => '2022-11-20',
                'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5,
                'energy' => EnergySource::Diesel, 'euro' => EuroStandard::Euro6d,
                'pollutant' => PollutantCategory::MostPolluting,
                'method' => HomologationMethod::Wltp, 'co2Wltp' => 155, 'pa' => 9, 'kerb' => 1700,
            ],
            // Peugeot Partner camionnette Diesel Euro 6 - N1 transport personnes.
            // Cas multi-VFC sur la même année (chantier E) : 3 VFC dont
            // 2 en 2024 — re-homologation CO₂ rétroactive en cours d'année.
            // Bascules : 130 g/km (2024-04-01) → 150 g/km (2024-09-01).
            // La 1ʳᵉ initiale reste à 145 g/km (saisie d'origine 2023).
            [
                'plate' => 'EH-008-HH', 'brand' => 'Peugeot', 'model' => 'Partner 2 rangs',
                'regFrench' => '2023-03-05', 'regOrigin' => '2023-03-05', 'econ' => '2023-03-05',
                'user' => VehicleUserType::CommercialVehicle, 'body' => BodyType::LightTruck, 'cat' => ReceptionCategory::N1, 'seats' => 5,
                'energy' => EnergySource::Diesel, 'euro' => EuroStandard::Euro6d,
                'pollutant' => PollutantCategory::MostPolluting,
                'method' => HomologationMethod::Wltp, 'co2Wltp' => 145, 'pa' => 7, 'kerb' => 1500,
                'n1PassengerTransport' => true,
                'extraVfcs' => [
                    ['from' => '2024-04-01', 'co2Wltp' => 130],
                    ['from' => '2024-09-01', 'co2Wltp' => 150],
                ],
            ],
            // Ford Transit Custom Diesel Euro 6 - utilitaire de transport
            [
                'plate' => 'EI-009-II', 'brand' => 'Ford', 'model' => 'Transit Custom',
                'regFrench' => '2020-08-10', 'regOrigin' => '2020-08-10', 'econ' => '2020-08-10',
                'user' => VehicleUserType::CommercialVehicle, 'body' => BodyType::LightTruck, 'cat' => ReceptionCategory::N1, 'seats' => 3,
                'energy' => EnergySource::Diesel, 'euro' => EuroStandard::Euro6,
                'pollutant' => PollutantCategory::MostPolluting,
                'method' => HomologationMethod::Wltp, 'co2Wltp' => 175, 'pa' => 9, 'kerb' => 2000,
                'n1PassengerTransport' => true,
            ],
            // Renault Kangoo handicap - exonération totale
            [
                'plate' => 'EJ-010-JJ', 'brand' => 'Renault', 'model' => 'Kangoo TPMR',
                'regFrench' => '2022-04-12', 'regOrigin' => '2022-04-12', 'econ' => '2022-04-12',
                'user' => VehicleUserType::PassengerCar, 'body' => BodyType::Handicap, 'cat' => ReceptionCategory::M1, 'seats' => 4,
                'energy' => EnergySource::Diesel, 'euro' => EuroStandard::Euro6d,
                'pollutant' => PollutantCategory::MostPolluting,
                'method' => HomologationMethod::Wltp, 'co2Wltp' => 130, 'pa' => 6, 'kerb' => 1450,
                'handicapAccess' => true,
            ],
            // Citroën C3 vendue mi-2025 - exerce la matrice de visibilité
            // (cf. ADR-0018 + chantier E.7) : présente dans la heatmap 2025
            // avec cells après 30/04 grisées, masquée dans la heatmap 2026+.
            [
                'plate' => 'EK-011-KK', 'brand' => 'Citroën', 'model' => 'C3',
                'regFrench' => '2019-09-01', 'regOrigin' => '2019-09-01', 'econ' => '2019-09-01',
                'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5,
                'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro6,
                'pollutant' => PollutantCategory::Category1,
                'method' => HomologationMethod::Wltp, 'co2Wltp' => 115, 'pa' => 5, 'kerb' => 1100,
                'exitDate' => '2025-04-30',
                'exitReason' => VehicleExitReason::Sold,
                'currentStatus' => VehicleStatus::Sold,
            ],
            // Renault Mégane VP essence Euro 6 — exerce le calcul fiscal
            // segmenté par VFC (chantier dette VFC). 1ʳᵉ VFC à 102 g/km
            // (saisie initiale), corrigée le 2024-06-16 à 145 g/km après
            // re-homologation. Le moteur fiscal doit appliquer la bonne
            // version à chaque période, pas l'actuelle (145) à toute
            // l'année 2024.
            [
                'plate' => 'EL-012-LL', 'brand' => 'Renault', 'model' => 'Mégane E-Tech',
                'regFrench' => '2023-08-20', 'regOrigin' => '2023-08-20', 'econ' => '2023-08-20',
                'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5,
                'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro6d,
                'pollutant' => PollutantCategory::Category1,
                'method' => HomologationMethod::Wltp, 'co2Wltp' => 102, 'pa' => 6, 'kerb' => 1380,
                // 2 VFC : 1ʳᵉ à 102 g/km (initiale 2023-08-20 → 2024-06-15),
                // 2ᵉ à 145 g/km (à partir du 2024-06-16, courante).
                'extraVfcs' => [
                    ['from' => '2024-06-16', 'co2Wltp' => 145],
                ],
            ],

            // ====================================================================
            // G1 · WLTP Cat1 Essence (10 véhicules · variété d'émissions 50-150 g)
            // ====================================================================
            ['plate' => 'EM-013-MM', 'brand' => 'Citroën', 'model' => 'C3', 'regFrench' => '2023-04-12', 'regOrigin' => '2023-04-12', 'econ' => '2023-04-12', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro6d, 'pollutant' => PollutantCategory::Category1, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 99, 'pa' => 5, 'kerb' => 1085],
            ['plate' => 'EN-014-NN', 'brand' => 'Fiat', 'model' => '500', 'regFrench' => '2022-07-10', 'regOrigin' => '2022-07-10', 'econ' => '2022-07-10', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 4, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro6d, 'pollutant' => PollutantCategory::Category1, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 90, 'pa' => 4, 'kerb' => 950],
            ['plate' => 'EO-015-OO', 'brand' => 'Volkswagen', 'model' => 'Polo', 'regFrench' => '2022-10-18', 'regOrigin' => '2022-10-18', 'econ' => '2022-10-18', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro6d, 'pollutant' => PollutantCategory::Category1, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 105, 'pa' => 5, 'kerb' => 1180],
            ['plate' => 'EP-016-PP', 'brand' => 'Mini', 'model' => 'Cooper', 'regFrench' => '2023-01-25', 'regOrigin' => '2023-01-25', 'econ' => '2023-01-25', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 4, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro6d, 'pollutant' => PollutantCategory::Category1, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 120, 'pa' => 6, 'kerb' => 1230],
            ['plate' => 'EQ-017-QQ', 'brand' => 'Audi', 'model' => 'A1 Sportback', 'regFrench' => '2023-06-08', 'regOrigin' => '2023-06-08', 'econ' => '2023-06-08', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro6d, 'pollutant' => PollutantCategory::Category1, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 130, 'pa' => 6, 'kerb' => 1265],
            ['plate' => 'ER-018-RR', 'brand' => 'BMW', 'model' => 'Série 1 118i', 'regFrench' => '2022-12-14', 'regOrigin' => '2022-12-14', 'econ' => '2022-12-14', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro6d, 'pollutant' => PollutantCategory::Category1, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 135, 'pa' => 7, 'kerb' => 1390],
            ['plate' => 'ES-019-SS', 'brand' => 'Mercedes', 'model' => 'Classe A 180', 'regFrench' => '2023-09-30', 'regOrigin' => '2023-09-30', 'econ' => '2023-09-30', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro6d, 'pollutant' => PollutantCategory::Category1, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 140, 'pa' => 7, 'kerb' => 1395],
            ['plate' => 'ET-020-TT', 'brand' => 'Volvo', 'model' => 'XC40', 'regFrench' => '2023-03-21', 'regOrigin' => '2023-03-21', 'econ' => '2023-03-21', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro6d, 'pollutant' => PollutantCategory::Category1, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 145, 'pa' => 7, 'kerb' => 1635],
            ['plate' => 'EU-021-UU', 'brand' => 'Honda', 'model' => 'Civic', 'regFrench' => '2022-08-22', 'regOrigin' => '2022-08-22', 'econ' => '2022-08-22', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro6d, 'pollutant' => PollutantCategory::Category1, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 115, 'pa' => 6, 'kerb' => 1320],
            ['plate' => 'EV-022-VV', 'brand' => 'Renault', 'model' => 'Captur', 'regFrench' => '2023-05-17', 'regOrigin' => '2023-05-17', 'econ' => '2023-05-17', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro6d, 'pollutant' => PollutantCategory::Category1, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 110, 'pa' => 5, 'kerb' => 1235],

            // ====================================================================
            // G2 · WLTP MostPolluting Diesel (7 véhicules)
            // ====================================================================
            ['plate' => 'EW-023-WW', 'brand' => 'Ford', 'model' => 'Transit', 'regFrench' => '2021-11-05', 'regOrigin' => '2021-11-05', 'econ' => '2021-11-05', 'user' => VehicleUserType::CommercialVehicle, 'body' => BodyType::LightTruck, 'cat' => ReceptionCategory::N1, 'seats' => 3, 'energy' => EnergySource::Diesel, 'euro' => EuroStandard::Euro6, 'pollutant' => PollutantCategory::MostPolluting, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 175, 'pa' => 9, 'kerb' => 2100, 'n1PassengerTransport' => true],
            ['plate' => 'EX-024-XX', 'brand' => 'Mercedes', 'model' => 'Vito', 'regFrench' => '2022-02-18', 'regOrigin' => '2022-02-18', 'econ' => '2022-02-18', 'user' => VehicleUserType::CommercialVehicle, 'body' => BodyType::LightTruck, 'cat' => ReceptionCategory::N1, 'seats' => 5, 'energy' => EnergySource::Diesel, 'euro' => EuroStandard::Euro6d, 'pollutant' => PollutantCategory::MostPolluting, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 170, 'pa' => 9, 'kerb' => 2050, 'n1PassengerTransport' => true],
            ['plate' => 'EY-025-YY', 'brand' => 'Peugeot', 'model' => '5008 BlueHDi', 'regFrench' => '2022-09-12', 'regOrigin' => '2022-09-12', 'econ' => '2022-09-12', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 7, 'energy' => EnergySource::Diesel, 'euro' => EuroStandard::Euro6d, 'pollutant' => PollutantCategory::MostPolluting, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 150, 'pa' => 8, 'kerb' => 1650],
            ['plate' => 'EZ-026-ZZ', 'brand' => 'Volkswagen', 'model' => 'Tiguan TDI', 'regFrench' => '2023-01-09', 'regOrigin' => '2023-01-09', 'econ' => '2023-01-09', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Diesel, 'euro' => EuroStandard::Euro6d, 'pollutant' => PollutantCategory::MostPolluting, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 160, 'pa' => 8, 'kerb' => 1730],
            ['plate' => 'FA-027-AA', 'brand' => 'Land Rover', 'model' => 'Defender', 'regFrench' => '2022-04-22', 'regOrigin' => '2022-04-22', 'econ' => '2022-04-22', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Diesel, 'euro' => EuroStandard::Euro6d, 'pollutant' => PollutantCategory::MostPolluting, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 190, 'pa' => 11, 'kerb' => 2200],
            ['plate' => 'FB-028-BB', 'brand' => 'Renault', 'model' => 'Master', 'regFrench' => '2021-06-30', 'regOrigin' => '2021-06-30', 'econ' => '2021-06-30', 'user' => VehicleUserType::CommercialVehicle, 'body' => BodyType::LightTruck, 'cat' => ReceptionCategory::N1, 'seats' => 3, 'energy' => EnergySource::Diesel, 'euro' => EuroStandard::Euro6, 'pollutant' => PollutantCategory::MostPolluting, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 195, 'pa' => 10, 'kerb' => 2400, 'n1PassengerTransport' => true],
            ['plate' => 'FC-029-CC', 'brand' => 'Audi', 'model' => 'Q5 TDI', 'regFrench' => '2023-07-18', 'regOrigin' => '2023-07-18', 'econ' => '2023-07-18', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Diesel, 'euro' => EuroStandard::Euro6d, 'pollutant' => PollutantCategory::MostPolluting, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 165, 'pa' => 9, 'kerb' => 1850],

            // ====================================================================
            // G3 · WLTP Cat E (élec/H₂) - 4 véhicules · exonération CO₂ + Cat E
            // ====================================================================
            ['plate' => 'FD-030-DD', 'brand' => 'Renault', 'model' => 'Zoé', 'regFrench' => '2023-02-08', 'regOrigin' => '2023-02-08', 'econ' => '2023-02-08', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Electric, 'euro' => null, 'pollutant' => PollutantCategory::E, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 0, 'pa' => 7, 'kerb' => 1502],
            ['plate' => 'FE-031-EE', 'brand' => 'Nissan', 'model' => 'Leaf', 'regFrench' => '2022-11-22', 'regOrigin' => '2022-11-22', 'econ' => '2022-11-22', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Electric, 'euro' => null, 'pollutant' => PollutantCategory::E, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 0, 'pa' => 6, 'kerb' => 1580],
            ['plate' => 'FF-032-FF', 'brand' => 'Hyundai', 'model' => 'Nexo', 'regFrench' => '2023-08-15', 'regOrigin' => '2023-08-15', 'econ' => '2023-08-15', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Hydrogen, 'euro' => null, 'pollutant' => PollutantCategory::E, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 0, 'pa' => 8, 'kerb' => 1814],
            ['plate' => 'FG-033-GG', 'brand' => 'Renault', 'model' => 'Master ZE', 'regFrench' => '2023-05-04', 'regOrigin' => '2023-05-04', 'econ' => '2023-05-04', 'user' => VehicleUserType::CommercialVehicle, 'body' => BodyType::LightTruck, 'cat' => ReceptionCategory::N1, 'seats' => 3, 'energy' => EnergySource::Electric, 'euro' => null, 'pollutant' => PollutantCategory::E, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 0, 'pa' => 8, 'kerb' => 2380, 'n1PassengerTransport' => true],

            // ====================================================================
            // G4 · NEDC Cat1 (essence ancien 2004-2020) - 4 véhicules
            // ====================================================================
            ['plate' => 'FH-034-HH', 'brand' => 'Citroën', 'model' => 'C4', 'regFrench' => '2014-03-15', 'regOrigin' => '2014-03-15', 'econ' => '2014-03-15', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro5, 'pollutant' => PollutantCategory::Category1, 'method' => HomologationMethod::Nedc, 'co2Nedc' => 145, 'pa' => 6, 'kerb' => 1250],
            ['plate' => 'FI-035-II', 'brand' => 'Ford', 'model' => 'Fiesta', 'regFrench' => '2016-06-20', 'regOrigin' => '2016-06-20', 'econ' => '2016-06-20', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro6, 'pollutant' => PollutantCategory::Category1, 'method' => HomologationMethod::Nedc, 'co2Nedc' => 125, 'pa' => 5, 'kerb' => 1135],
            ['plate' => 'FJ-036-JJ', 'brand' => 'Renault', 'model' => 'Mégane III', 'regFrench' => '2015-10-12', 'regOrigin' => '2015-10-12', 'econ' => '2015-10-12', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro6, 'pollutant' => PollutantCategory::Category1, 'method' => HomologationMethod::Nedc, 'co2Nedc' => 140, 'pa' => 6, 'kerb' => 1290],
            ['plate' => 'FK-037-KK', 'brand' => 'Volkswagen', 'model' => 'Golf VII', 'regFrench' => '2014-08-08', 'regOrigin' => '2014-08-08', 'econ' => '2014-08-08', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro5, 'pollutant' => PollutantCategory::Category1, 'method' => HomologationMethod::Nedc, 'co2Nedc' => 138, 'pa' => 6, 'kerb' => 1280],

            // ====================================================================
            // G5 · NEDC MostPolluting Diesel (ancien) - 4 véhicules
            // ====================================================================
            ['plate' => 'FL-038-LL', 'brand' => 'Peugeot', 'model' => '308 HDi', 'regFrench' => '2013-09-05', 'regOrigin' => '2013-09-05', 'econ' => '2013-09-05', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Diesel, 'euro' => EuroStandard::Euro5, 'pollutant' => PollutantCategory::MostPolluting, 'method' => HomologationMethod::Nedc, 'co2Nedc' => 150, 'pa' => 7, 'kerb' => 1450],
            ['plate' => 'FM-039-MM', 'brand' => 'Citroën', 'model' => 'Berlingo HDi', 'regFrench' => '2015-04-22', 'regOrigin' => '2015-04-22', 'econ' => '2015-04-22', 'user' => VehicleUserType::CommercialVehicle, 'body' => BodyType::LightTruck, 'cat' => ReceptionCategory::N1, 'seats' => 5, 'energy' => EnergySource::Diesel, 'euro' => EuroStandard::Euro5, 'pollutant' => PollutantCategory::MostPolluting, 'method' => HomologationMethod::Nedc, 'co2Nedc' => 160, 'pa' => 7, 'kerb' => 1620, 'n1PassengerTransport' => true],
            ['plate' => 'FN-040-NN', 'brand' => 'Renault', 'model' => 'Kangoo dCi', 'regFrench' => '2014-11-18', 'regOrigin' => '2014-11-18', 'econ' => '2014-11-18', 'user' => VehicleUserType::CommercialVehicle, 'body' => BodyType::LightTruck, 'cat' => ReceptionCategory::N1, 'seats' => 5, 'energy' => EnergySource::Diesel, 'euro' => EuroStandard::Euro5, 'pollutant' => PollutantCategory::MostPolluting, 'method' => HomologationMethod::Nedc, 'co2Nedc' => 155, 'pa' => 6, 'kerb' => 1500, 'n1PassengerTransport' => true, 'n1RemovableSecondRowSeat' => true],
            ['plate' => 'FO-041-OO', 'brand' => 'Ford', 'model' => 'Focus TDCi', 'regFrench' => '2016-02-29', 'regOrigin' => '2016-02-29', 'econ' => '2016-02-29', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Diesel, 'euro' => EuroStandard::Euro6, 'pollutant' => PollutantCategory::MostPolluting, 'method' => HomologationMethod::Nedc, 'co2Nedc' => 145, 'pa' => 7, 'kerb' => 1380],

            // ====================================================================
            // G6 · PA vintage (avant 2006) - 5 véhicules · stress barème PA
            // ====================================================================
            ['plate' => 'FP-042-PP', 'brand' => 'Peugeot', 'model' => '405', 'regFrench' => '1998-06-15', 'regOrigin' => '1998-06-15', 'econ' => '1998-06-15', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro2, 'pollutant' => PollutantCategory::MostPolluting, 'method' => HomologationMethod::Pa, 'pa' => 6, 'kerb' => 1100],
            ['plate' => 'FQ-043-QQ', 'brand' => 'Citroën', 'model' => 'AX', 'regFrench' => '1995-03-10', 'regOrigin' => '1995-03-10', 'econ' => '1995-03-10', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 4, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro1, 'pollutant' => PollutantCategory::MostPolluting, 'method' => HomologationMethod::Pa, 'pa' => 4, 'kerb' => 700],
            ['plate' => 'FR-044-RR', 'brand' => 'Renault', 'model' => '19 Chamade', 'regFrench' => '1993-09-22', 'regOrigin' => '1993-09-22', 'econ' => '1993-09-22', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Gasoline, 'euro' => null, 'pollutant' => PollutantCategory::MostPolluting, 'method' => HomologationMethod::Pa, 'pa' => 5, 'kerb' => 880],
            ['plate' => 'FS-045-SS', 'brand' => 'Mercedes', 'model' => '190E', 'regFrench' => '1991-08-04', 'regOrigin' => '1991-08-04', 'econ' => '1991-08-04', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Gasoline, 'euro' => null, 'pollutant' => PollutantCategory::MostPolluting, 'method' => HomologationMethod::Pa, 'pa' => 8, 'kerb' => 1180],
            ['plate' => 'FT-046-TT', 'brand' => 'BMW', 'model' => 'E30 320i', 'regFrench' => '1989-05-18', 'regOrigin' => '1989-05-18', 'econ' => '1989-05-18', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Gasoline, 'euro' => null, 'pollutant' => PollutantCategory::MostPolluting, 'method' => HomologationMethod::Pa, 'pa' => 9, 'kerb' => 1170],

            // ====================================================================
            // G7 · E85 cas spéciaux (abattement 40 % WLTP / 2 CV PA) - 5 véhicules
            // ====================================================================
            // E85 WLTP 130 → 78 g/km après abatement (test 2025/2026)
            ['plate' => 'FU-047-UU', 'brand' => 'Renault', 'model' => 'Captur E85', 'regFrench' => '2023-04-12', 'regOrigin' => '2023-04-12', 'econ' => '2023-04-12', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro6d, 'pollutant' => PollutantCategory::Category1, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 130, 'pa' => 6, 'kerb' => 1340, 'acceptsE85' => true],
            // E85 WLTP 100 → 60 g/km (cas BOFiP § 240)
            ['plate' => 'FV-048-VV', 'brand' => 'Ford', 'model' => 'Focus E85', 'regFrench' => '2022-09-10', 'regOrigin' => '2022-09-10', 'econ' => '2022-09-10', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro6d, 'pollutant' => PollutantCategory::Category1, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 100, 'pa' => 6, 'kerb' => 1380, 'acceptsE85' => true],
            // E85 WLTP 251 g/km → HORS PLAFOND (perte abatement, taxe pleine)
            ['plate' => 'FW-049-WW', 'brand' => 'Dacia', 'model' => 'Duster Bi-Fuel E85', 'regFrench' => '2023-07-08', 'regOrigin' => '2023-07-08', 'econ' => '2023-07-08', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro6d, 'pollutant' => PollutantCategory::Category1, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 251, 'pa' => 8, 'kerb' => 1480, 'acceptsE85' => true],
            // E85 PA 12 CV → BORNE INCLUSIVE (garde abatement → 10 CV)
            ['plate' => 'FX-050-XX', 'brand' => 'Renault', 'model' => 'Safrane V6 E85', 'regFrench' => '1997-04-22', 'regOrigin' => '1997-04-22', 'econ' => '1997-04-22', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro1, 'pollutant' => PollutantCategory::MostPolluting, 'method' => HomologationMethod::Pa, 'pa' => 12, 'kerb' => 1620, 'acceptsE85' => true],
            // E85 PA 13 CV → BORNE EXCLUSIVE (perte abatement)
            ['plate' => 'FY-051-YY', 'brand' => 'Citroën', 'model' => 'XM Pallas E85', 'regFrench' => '1996-11-15', 'regOrigin' => '1996-11-15', 'econ' => '1996-11-15', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro2, 'pollutant' => PollutantCategory::MostPolluting, 'method' => HomologationMethod::Pa, 'pa' => 13, 'kerb' => 1580, 'acceptsE85' => true],

            // ====================================================================
            // G8 · Handicap + Hybride conditionnel R-2024-017 - 2 véhicules
            // ====================================================================
            // Handicap_access supplémentaire (zeroing total)
            ['plate' => 'FZ-052-ZZ', 'brand' => 'Volkswagen', 'model' => 'Caddy TPMR', 'regFrench' => '2022-08-30', 'regOrigin' => '2022-08-30', 'econ' => '2022-08-30', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::Handicap, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Diesel, 'euro' => EuroStandard::Euro6d, 'pollutant' => PollutantCategory::MostPolluting, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 155, 'pa' => 7, 'kerb' => 1620, 'handicapAccess' => true],
            // Hybride non-rechargeable essence faible CO₂ · éligible R-2024-017 régime général (WLTP ≤ 60)
            ['plate' => 'GA-053-AA', 'brand' => 'Toyota', 'model' => 'Prius Hybrid', 'regFrench' => '2020-05-04', 'regOrigin' => '2020-05-04', 'econ' => '2020-05-04', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::NonPluginHybrid, 'underlying' => UnderlyingCombustionEngineType::Gasoline, 'euro' => EuroStandard::Euro6d, 'pollutant' => PollutantCategory::Category1, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 45, 'pa' => 5, 'kerb' => 1390],

            // ====================================================================
            // G9 · Hors champ M1/N1 (R-XXXX-004) - 5 véhicules
            // ====================================================================
            // M1 ambulance (m1_special_use)
            ['plate' => 'GB-054-BB', 'brand' => 'Mercedes', 'model' => 'Sprinter Ambulance', 'regFrench' => '2022-12-01', 'regOrigin' => '2022-12-01', 'econ' => '2022-12-01', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Diesel, 'euro' => EuroStandard::Euro6d, 'pollutant' => PollutantCategory::MostPolluting, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 180, 'pa' => 9, 'kerb' => 2400, 'm1SpecialUse' => true],
            // M1 corbillard
            ['plate' => 'GC-055-CC', 'brand' => 'Renault', 'model' => 'Master Funéraire', 'regFrench' => '2021-04-20', 'regOrigin' => '2021-04-20', 'econ' => '2021-04-20', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 3, 'energy' => EnergySource::Diesel, 'euro' => EuroStandard::Euro6, 'pollutant' => PollutantCategory::MostPolluting, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 195, 'pa' => 10, 'kerb' => 2350, 'm1SpecialUse' => true],
            // N1 fourgon sans 2ème rangée + sans passenger_transport (HORS CHAMP)
            ['plate' => 'GD-056-DD', 'brand' => 'Iveco', 'model' => 'Daily Fourgon', 'regFrench' => '2022-06-15', 'regOrigin' => '2022-06-15', 'econ' => '2022-06-15', 'user' => VehicleUserType::CommercialVehicle, 'body' => BodyType::LightTruck, 'cat' => ReceptionCategory::N1, 'seats' => 3, 'energy' => EnergySource::Diesel, 'euro' => EuroStandard::Euro6, 'pollutant' => PollutantCategory::MostPolluting, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 200, 'pa' => 10, 'kerb' => 2700],
            // N1 pickup 5 places skiable (HORS CHAMP par remontées mécaniques)
            ['plate' => 'GE-057-EE', 'brand' => 'Toyota', 'model' => 'Hilux Pickup', 'regFrench' => '2023-01-12', 'regOrigin' => '2023-01-12', 'econ' => '2023-01-12', 'user' => VehicleUserType::CommercialVehicle, 'body' => BodyType::Pickup, 'cat' => ReceptionCategory::N1, 'seats' => 5, 'energy' => EnergySource::Diesel, 'euro' => EuroStandard::Euro6d, 'pollutant' => PollutantCategory::MostPolluting, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 220, 'pa' => 11, 'kerb' => 2100, 'n1SkiLiftUse' => true],
            // N1 station wagon générique (HORS CHAMP)
            ['plate' => 'GF-058-FF', 'brand' => 'Land Rover', 'model' => 'Discovery N1', 'regFrench' => '2022-03-30', 'regOrigin' => '2022-03-30', 'econ' => '2022-03-30', 'user' => VehicleUserType::CommercialVehicle, 'body' => BodyType::StationWagon, 'cat' => ReceptionCategory::N1, 'seats' => 5, 'energy' => EnergySource::Diesel, 'euro' => EuroStandard::Euro6d, 'pollutant' => PollutantCategory::MostPolluting, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 205, 'pa' => 11, 'kerb' => 2350],

            // ====================================================================
            // G10 · Cycle de vie · sorties de flotte (5 véhicules supplémentaires)
            // ====================================================================
            // Sorti mid-2024 · Sold (en plus de EK-011-KK déjà existant)
            ['plate' => 'GG-059-GG', 'brand' => 'Peugeot', 'model' => '208', 'regFrench' => '2019-07-22', 'regOrigin' => '2019-07-22', 'econ' => '2019-07-22', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro6, 'pollutant' => PollutantCategory::Category1, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 110, 'pa' => 5, 'kerb' => 1090, 'exitDate' => '2024-08-15', 'exitReason' => VehicleExitReason::Sold, 'currentStatus' => VehicleStatus::Sold],
            // Sorti mid-2025 · Destroyed
            ['plate' => 'GH-060-HH', 'brand' => 'Renault', 'model' => 'Twingo', 'regFrench' => '2020-04-08', 'regOrigin' => '2020-04-08', 'econ' => '2020-04-08', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 4, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro6d, 'pollutant' => PollutantCategory::Category1, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 100, 'pa' => 4, 'kerb' => 980, 'exitDate' => '2025-07-20', 'exitReason' => VehicleExitReason::Destroyed, 'currentStatus' => VehicleStatus::Destroyed],
            // Sorti mid-2026 · StolenUnrecovered
            ['plate' => 'GI-061-II', 'brand' => 'Citroën', 'model' => 'C1', 'regFrench' => '2018-11-12', 'regOrigin' => '2018-11-12', 'econ' => '2018-11-12', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 4, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro6, 'pollutant' => PollutantCategory::Category1, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 95, 'pa' => 4, 'kerb' => 850, 'exitDate' => '2026-04-30', 'exitReason' => VehicleExitReason::StolenUnrecovered, 'currentStatus' => VehicleStatus::Other],
            // Sorti mid-2025 · Sold (autre)
            ['plate' => 'GJ-062-JJ', 'brand' => 'Volkswagen', 'model' => 'Up!', 'regFrench' => '2019-02-14', 'regOrigin' => '2019-02-14', 'econ' => '2019-02-14', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 4, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro6d, 'pollutant' => PollutantCategory::Category1, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 105, 'pa' => 5, 'kerb' => 990, 'exitDate' => '2025-10-31', 'exitReason' => VehicleExitReason::Sold, 'currentStatus' => VehicleStatus::Sold],
            // Multi-VFC riche · activation E85 mid-2025
            ['plate' => 'GK-063-KK', 'brand' => 'Dacia', 'model' => 'Sandero', 'regFrench' => '2024-01-15', 'regOrigin' => '2024-01-15', 'econ' => '2024-01-15', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro6d, 'pollutant' => PollutantCategory::Category1, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 120, 'pa' => 5, 'kerb' => 1150, 'extraVfcs' => [['from' => '2025-07-01', 'acceptsE85' => true]]],
            // Multi-VFC riche · changement PollutantCategory mid-2025 (Cat1 → MostPolluting via dégradation Euro)
            ['plate' => 'GL-064-LL', 'brand' => 'Suzuki', 'model' => 'Swift', 'regFrench' => '2024-02-20', 'regOrigin' => '2024-02-20', 'econ' => '2024-02-20', 'user' => VehicleUserType::PassengerCar, 'body' => BodyType::InteriorDriving, 'cat' => ReceptionCategory::M1, 'seats' => 5, 'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro6, 'pollutant' => PollutantCategory::Category1, 'method' => HomologationMethod::Wltp, 'co2Wltp' => 125, 'pa' => 5, 'kerb' => 1080, 'extraVfcs' => [['from' => '2025-09-01', 'pollutant' => PollutantCategory::MostPolluting, 'euro' => EuroStandard::Euro4]]],
        ];

        $created = [];
        foreach ($specs as $spec) {
            $vehicle = Vehicle::updateOrCreate(
                ['license_plate' => $spec['plate']],
                [
                    'brand' => $spec['brand'],
                    'model' => $spec['model'],
                    'first_french_registration_date' => Carbon::parse($spec['regFrench']),
                    'first_origin_registration_date' => Carbon::parse($spec['regOrigin']),
                    'first_economic_use_date' => Carbon::parse($spec['econ']),
                    'acquisition_date' => Carbon::parse($spec['econ']),
                    'current_status' => $spec['currentStatus'] ?? VehicleStatus::Active,
                    'exit_date' => isset($spec['exitDate']) ? Carbon::parse($spec['exitDate']) : null,
                    'exit_reason' => $spec['exitReason'] ?? null,
                ],
            );

            // Caractéristiques fiscales initiales - une seule version courante.
            $vehicle->fiscalCharacteristics()->delete();
            VehicleFiscalCharacteristics::create([
                'vehicle_id' => $vehicle->id,
                'effective_from' => Carbon::parse($spec['regFrench']),
                'effective_to' => null,
                'reception_category' => $spec['cat'],
                'vehicle_user_type' => $spec['user'],
                'body_type' => $spec['body'],
                'seats_count' => $spec['seats'],
                'energy_source' => $spec['energy'],
                'underlying_combustion_engine_type' => $spec['underlying'] ?? null,
                'euro_standard' => $spec['euro'] ?? null,
                'pollutant_category' => $spec['pollutant'],
                'homologation_method' => $spec['method'],
                'co2_wltp' => $spec['co2Wltp'] ?? null,
                'co2_nedc' => $spec['co2Nedc'] ?? null,
                'taxable_horsepower' => $spec['pa'],
                'kerb_mass' => $spec['kerb'] ?? null,
                'handicap_access' => $spec['handicapAccess'] ?? false,
                'accepts_e85' => $spec['acceptsE85'] ?? false,
                'n1_passenger_transport' => $spec['n1PassengerTransport'] ?? false,
                'n1_removable_second_row_seat' => $spec['n1RemovableSecondRowSeat'] ?? false,
                'm1_special_use' => $spec['m1SpecialUse'] ?? false,
                'n1_ski_lift_use' => $spec['n1SkiLiftUse'] ?? false,
                'change_reason' => FiscalCharacteristicsChangeReason::InitialCreation,
            ]);

            $created[$spec['plate']] = $vehicle;
        }

        // Cas multi-VFC (chantier E) : pour les véhicules marqués
        // `extraVfcs`, on ajoute N transitions VFC supplémentaires.
        // Pour chaque entrée, on ferme la précédente courante au jour
        // précédent et on crée une nouvelle VFC en reprenant les champs
        // hérités de la spec puis en surchargeant les champs présents
        // dans l'entrée (`co2Wltp`, `co2Nedc`, `pa`, `energy`, etc.).
        // Permet d'exercer visuellement le segmenteur fiscal sur la
        // fiche véhicule (1, 2, 3, N versions par véhicule).
        foreach ($specs as $spec) {
            if (! isset($spec['extraVfcs']) || $spec['extraVfcs'] === []) {
                continue;
            }
            $vehicle = $created[$spec['plate']];

            // Hérite des champs immuables du spec ; chaque extraVfc peut
            // surcharger un sous-ensemble (CO₂, PA, énergie, etc.).
            $inheritedFields = [
                'reception_category' => $spec['cat'],
                'vehicle_user_type' => $spec['user'],
                'body_type' => $spec['body'],
                'seats_count' => $spec['seats'],
                'energy_source' => $spec['energy'],
                'underlying_combustion_engine_type' => $spec['underlying'] ?? null,
                'euro_standard' => $spec['euro'] ?? null,
                'pollutant_category' => $spec['pollutant'],
                'homologation_method' => $spec['method'],
                'co2_wltp' => $spec['co2Wltp'] ?? null,
                'co2_nedc' => $spec['co2Nedc'] ?? null,
                'taxable_horsepower' => $spec['pa'],
                'kerb_mass' => $spec['kerb'] ?? null,
                'handicap_access' => $spec['handicapAccess'] ?? false,
                'accepts_e85' => $spec['acceptsE85'] ?? false,
                'n1_passenger_transport' => $spec['n1PassengerTransport'] ?? false,
                'n1_removable_second_row_seat' => $spec['n1RemovableSecondRowSeat'] ?? false,
                'm1_special_use' => $spec['m1SpecialUse'] ?? false,
                'n1_ski_lift_use' => $spec['n1SkiLiftUse'] ?? false,
                'change_reason' => FiscalCharacteristicsChangeReason::Recharacterization,
            ];

            foreach ($spec['extraVfcs'] as $entry) {
                $effectiveFrom = Carbon::parse($entry['from']);
                // Ferme la VFC actuellement courante (effective_to=null)
                // au jour précédent.
                $current = $vehicle->fiscalCharacteristics()
                    ->whereNull('effective_to')
                    ->latest('effective_from')
                    ->firstOrFail();
                $current->update([
                    'effective_to' => $effectiveFrom->copy()->subDay(),
                ]);

                $fields = $inheritedFields;
                // Surcharges optionnelles côté entry.
                if (array_key_exists('co2Wltp', $entry)) {
                    $fields['co2_wltp'] = $entry['co2Wltp'];
                }
                if (array_key_exists('co2Nedc', $entry)) {
                    $fields['co2_nedc'] = $entry['co2Nedc'];
                }
                if (array_key_exists('pa', $entry)) {
                    $fields['taxable_horsepower'] = $entry['pa'];
                }
                if (array_key_exists('energy', $entry)) {
                    $fields['energy_source'] = $entry['energy'];
                }
                if (array_key_exists('euro', $entry)) {
                    $fields['euro_standard'] = $entry['euro'];
                }
                if (array_key_exists('pollutant', $entry)) {
                    $fields['pollutant_category'] = $entry['pollutant'];
                }
                if (array_key_exists('method', $entry)) {
                    $fields['homologation_method'] = $entry['method'];
                }
                if (array_key_exists('acceptsE85', $entry)) {
                    $fields['accepts_e85'] = $entry['acceptsE85'];
                }

                VehicleFiscalCharacteristics::create([
                    'vehicle_id' => $vehicle->id,
                    'effective_from' => $effectiveFrom,
                    'effective_to' => null,
                    ...$fields,
                ]);
            }
        }

        return $created;
    }

    /**
     * Contrats démo sur les 3 années (2024, 2025, 2026) couvrant tous les
     * cas particuliers fiscaux · LLD/LCD, cross-année, scissions ADR-0022,
     * multi-VFC, clusters de risque pré-déclaration. Une entrée du plan =
     * un contrat. Le `contract_type` est déduit de la durée (≤ 30 j →
     * `lcd`, sinon `lld`).
     *
     * @param  array<string, Vehicle>  $vehicles
     * @param  array<string, Company>  $companies
     */
    private function seedContracts(array $vehicles, array $companies): void
    {
        // Nettoyage global · on repart à blanc pour les 3 années.
        Contract::query()->forceDelete();

        $plans = array_merge(
            $this->buildContractPlan2024(),
            $this->buildContractPlan2025(),
            $this->buildContractPlan2026(),
        );

        foreach ($plans as $row) {
            $vehicle = $vehicles[$row['plate']] ?? null;
            $company = $companies[$row['company']] ?? null;
            if ($vehicle === null || $company === null) {
                continue;
            }

            // Ne pas créer de contrat après la sortie de flotte.
            $end = Carbon::parse($row['to']);
            if ($vehicle->exit_date !== null && $end->greaterThan($vehicle->exit_date)) {
                continue;
            }

            $start = Carbon::parse($row['from']);
            $duration = $start->diffInDays($end) + 1;
            $type = $duration <= 30 ? ContractType::Lcd : ContractType::Lld;

            // Tolérance overlap · le trigger MySQL `contracts: overlapping
            // period` peut rejeter un contrat si une plage chevauche un
            // autre contrat du même véhicule. Pour un seeder de démo
            // évolutif, on log et on continue plutôt que de bloquer tout.
            try {
                Contract::create([
                    'vehicle_id' => $vehicle->id,
                    'company_id' => $company->id,
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                    'contract_reference' => $row['ref'] ?? null,
                    'contract_type' => $type,
                    'notes' => $row['notes'] ?? null,
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                if (str_contains($e->getMessage(), 'overlapping period')) {
                    $this->command?->warn(sprintf(
                        "Skipped overlap · %s × %s · %s → %s",
                        $row['plate'],
                        $row['company'],
                        $row['from'],
                        $row['to'],
                    ));

                    continue;
                }
                throw $e;
            }
        }
    }

    /**
     * Indispos seedées pour exercer la grille ADR-0016 rev. 1.1 en démo.
     * Couvre 2 axes :
     *  - **Hors contrats** (4 entrées historiques) : indispos isolées —
     *    exerce le calcul autonome.
     *  - **Cohabitant avec contrats** (4 entrées chantier E) : indispo
     *    sur une plage qui chevauche un contrat actif. Cas autorisé par
     *    ADR-0019 — le moteur fiscal retire les jours d'indispo
     *    réductrice du prorata du contrat (et ignore les non-réductrices).
     *  - **Cas mixte** : 1 entrée chevauche À LA FOIS un contrat ET une
     *    bascule VFC — exerce conjointement le segmenteur VFC + la
     *    réduction prorata.
     *
     * Le trigger MySQL anti-overlap n'agit qu'entre contrats (pas entre
     * indispos et contrats), donc la cohabitation ne pose aucun problème
     * d'insertion. Les plages cohabitantes sont volontairement situées
     * dans des contrats long-terme (LLD) où l'effet du retrait de jours
     * est mesurable.
     *
     * @param  array<string, Vehicle>  $vehicles
     */
    private function seedUnavailabilities(array $vehicles): void
    {
        // Nettoyage global · on repart à blanc sur les 3 années.
        Unavailability::query()->forceDelete();

        // === Indispos HORS contrats (cas standalone) ============================

        // EJ-010-JJ Kangoo TPMR - créneau libre avant le 1er contrat COR
        // du 03-04. Fourrière publique 8 j, réductrice.
        $this->createUnavailability(
            vehicle: $vehicles['EJ-010-JJ'],
            type: UnavailabilityType::PoundPublic,
            startDate: '2024-02-12',
            endDate: '2024-02-19',
            description: 'Stationnement gênant signalé par la mairie.',
        );

        // EI-009-II Ford Transit - créneau libre 06-01 → 09-30 (entre BTP et ECO).
        // Interdiction de circuler post-sinistre 12 j, réductrice.
        $this->createUnavailability(
            vehicle: $vehicles['EI-009-II'],
            type: UnavailabilityType::AccidentNoCirculation,
            startDate: '2024-07-08',
            endDate: '2024-07-19',
            description: 'Choc latéral, expertise + interdiction préfectorale.',
        );

        // EG-007-GG BMW Série 5 - créneau hors contrats. Suspension CI 25 j,
        // réductrice (max BOFiP § 50).
        $this->createUnavailability(
            vehicle: $vehicles['EG-007-GG'],
            type: UnavailabilityType::CiSuspension,
            startDate: '2024-08-05',
            endDate: '2024-08-29',
            description: 'Suspension administrative du certificat d\'immatriculation.',
        );

        // EH-008-HH Partner - maintenance courante 4 j, NON réductrice
        // (BOFiP § 50 : entretien courant exclu).
        $this->createUnavailability(
            vehicle: $vehicles['EH-008-HH'],
            type: UnavailabilityType::Maintenance,
            startDate: '2024-12-09',
            endDate: '2024-12-12',
            description: 'Révision constructeur + remplacement pneus AV.',
        );

        // === Indispos COHABITANT avec contrats (chantier E) =====================

        // EA-001-AA Peugeot 308 - chevauche le contrat ACM 01-08 → 02-29.
        // Suspension CI 11 j → réductrice : la taxe ACM doit être réduite
        // au prorata sur les 53 j du contrat.
        $this->createUnavailability(
            vehicle: $vehicles['EA-001-AA'],
            type: UnavailabilityType::CiSuspension,
            startDate: '2024-02-15',
            endDate: '2024-02-25',
            description: 'Cohabitation contrat ACM : suspension administrative pour défaut d\'assurance.',
        );

        // EB-002-BB Renault Trafic - chevauche le contrat BTP 01-15 → 04-30.
        // Accident sans circulation 10 j → réductrice : la taxe BTP doit
        // être réduite (utile car BTP est long, le delta est visible sur
        // le breakdown fiscal).
        $this->createUnavailability(
            vehicle: $vehicles['EB-002-BB'],
            type: UnavailabilityType::AccidentNoCirculation,
            startDate: '2024-03-10',
            endDate: '2024-03-19',
            description: 'Cohabitation contrat BTP : choc à l\'arrière, attente expertise.',
        );

        // EE-005-EE Renault 21 - **cas mixte** : chevauche le contrat COR
        // 03-04 → 03-28 ET la bascule VFC 03-15 (PA 8→7). Exerce
        // simultanément le segmenteur VFC + la réduction prorata.
        $this->createUnavailability(
            vehicle: $vehicles['EE-005-EE'],
            type: UnavailabilityType::CiSuspension,
            startDate: '2024-03-10',
            endDate: '2024-03-22',
            description: 'Cohabitation contrat COR + bascule VFC : suspension CI 13 j à cheval sur 2 versions PA.',
        );

        // EH-008-HH Partner - chevauche le contrat BTP 01-08 → 03-15.
        // Maintenance 4 j NON réductrice → le moteur doit l\'IGNORER :
        // la taxe BTP ne doit PAS être réduite. Garde-fou anti-régression.
        $this->createUnavailability(
            vehicle: $vehicles['EH-008-HH'],
            type: UnavailabilityType::Maintenance,
            startDate: '2024-02-12',
            endDate: '2024-02-15',
            description: 'Cohabitation contrat BTP : entretien courant (NE doit PAS réduire la taxe).',
        );

        // ====================================================================
        // === Indispos 2025 (~15) · diversifiées + cross-année 2024/2025
        // ====================================================================

        // Indispo chevauchant 2024 et 2025 (test décompte par année)
        $this->createUnavailability(
            vehicle: $vehicles['EI-009-II'],
            type: UnavailabilityType::PoundPublic,
            startDate: '2024-12-20',
            endDate: '2025-01-10',
            description: 'Cross-année · 12j en 2024 + 10j en 2025.',
        );

        // Fourrière publique 15 jours sur véhicule contractuel
        $this->createUnavailability(
            vehicle: $vehicles['EA-001-AA'],
            type: UnavailabilityType::PoundPublic,
            startDate: '2025-04-05',
            endDate: '2025-04-19',
            description: 'Fourrière publique 15j 2025 · réductrice.',
        );

        // Suspension CI 59 jours (cas BOFiP)
        $this->createUnavailability(
            vehicle: $vehicles['EB-002-BB'],
            type: UnavailabilityType::CiSuspension,
            startDate: '2025-02-01',
            endDate: '2025-03-31',
            description: 'Suspension CI 59j non bissextile · cas BOFiP.',
        );

        // Interdiction circulation 30 jours sur véhicule LCD (test anti double-décompte)
        $this->createUnavailability(
            vehicle: $vehicles['EM-013-MM'],
            type: UnavailabilityType::AccidentNoCirculation,
            startDate: '2025-03-01',
            endDate: '2025-03-30',
            description: 'Interdiction circulation 30j 2025 sur véhicule actif LLD.',
        );

        // Maintenance hors fiscal sur véhicule contractuel (garde-fou)
        $this->createUnavailability(
            vehicle: $vehicles['EF-006-FF'],
            type: UnavailabilityType::Maintenance,
            startDate: '2025-06-10',
            endDate: '2025-06-15',
            description: 'Maintenance 5j 2025 · NON réductrice (garde-fou).',
        );

        // Fourrière privée (NON réductrice)
        $this->createUnavailability(
            vehicle: $vehicles['EG-007-GG'],
            type: UnavailabilityType::PoundPrivate,
            startDate: '2025-05-12',
            endDate: '2025-05-19',
            description: 'Fourrière privée 7j 2025 · NON réductrice.',
        );

        // Vol simple (NON réductrice)
        $this->createUnavailability(
            vehicle: $vehicles['EH-008-HH'],
            type: UnavailabilityType::Theft,
            startDate: '2025-08-04',
            endDate: '2025-08-18',
            description: 'Vol simple 14j 2025 · NON réductrice.',
        );

        // Contrôle technique 2 jours (NON réductrice)
        $this->createUnavailability(
            vehicle: $vehicles['EJ-010-JJ'],
            type: UnavailabilityType::TechnicalInspection,
            startDate: '2025-09-08',
            endDate: '2025-09-09',
            description: 'Contrôle technique CT 2j 2025.',
        );

        // Réparation accident simple (NON réductrice)
        $this->createUnavailability(
            vehicle: $vehicles['EL-012-LL'],
            type: UnavailabilityType::AccidentRepair,
            startDate: '2025-07-22',
            endDate: '2025-08-04',
            description: 'Réparation accident 14j 2025 · NON réductrice.',
        );

        // Cumul réductrice + non réductrice sur même véhicule en 2025
        $this->createUnavailability(
            vehicle: $vehicles['EQ-017-QQ'],
            type: UnavailabilityType::PoundPublic,
            startDate: '2025-04-01',
            endDate: '2025-04-15',
            description: 'Réductrice 15j · 1/2 cumul EQ-017-QQ',
        );
        $this->createUnavailability(
            vehicle: $vehicles['EQ-017-QQ'],
            type: UnavailabilityType::Maintenance,
            startDate: '2025-05-10',
            endDate: '2025-05-25',
            description: 'NON réductrice 16j · 2/2 cumul EQ-017-QQ',
        );

        // Suspension CI mid-2025 sur véhicule Multi-VFC
        $this->createUnavailability(
            vehicle: $vehicles['GK-063-KK'],
            type: UnavailabilityType::CiSuspension,
            startDate: '2025-06-20',
            endDate: '2025-07-08',
            description: 'Suspension CI à cheval bascule VFC E85 01/07/2025.',
        );

        // Fourrière publique pendant LCD (anti double-décompte avec R-2025-021)
        $this->createUnavailability(
            vehicle: $vehicles['EM-013-MM'],
            type: UnavailabilityType::PoundPublic,
            startDate: '2025-01-15',
            endDate: '2025-01-20',
            description: 'Réductrice 6j PENDANT LCD · anti double-décompte R-2025-021.',
        );

        // ====================================================================
        // === Indispos 2026 (~15) · scissions + cross-année 2025/2026
        // ====================================================================

        // Cross 2025/2026
        $this->createUnavailability(
            vehicle: $vehicles['EA-001-AA'],
            type: UnavailabilityType::PoundPublic,
            startDate: '2025-12-15',
            endDate: '2026-01-08',
            description: 'Cross 2025/2026 · 17j en 2025 + 8j en 2026.',
        );

        // Fourrière publique 30j à cheval scission polluants 01/03/2026
        $this->createUnavailability(
            vehicle: $vehicles['EM-013-MM'],
            type: UnavailabilityType::PoundPublic,
            startDate: '2026-02-15',
            endDate: '2026-03-16',
            description: 'Réductrice 30j à cheval scission polluants 01/03/2026.',
        );

        // Suspension CI 45j
        $this->createUnavailability(
            vehicle: $vehicles['EB-002-BB'],
            type: UnavailabilityType::CiSuspension,
            startDate: '2026-05-10',
            endDate: '2026-06-23',
            description: 'Suspension CI 45j 2026.',
        );

        // Interdiction circulation 20j sur véhicule LCD cluster (Cluster gaming?)
        $this->createUnavailability(
            vehicle: $vehicles['EX-024-XX'],
            type: UnavailabilityType::AccidentNoCirculation,
            startDate: '2026-08-10',
            endDate: '2026-08-29',
            description: 'Interdiction circulation 20j à cheval cluster LCD.',
        );

        // Maintenance 5j sur véhicule IDF post LF 2026 art. 60
        $this->createUnavailability(
            vehicle: $vehicles['EV-022-VV'],
            type: UnavailabilityType::Maintenance,
            startDate: '2026-07-10',
            endDate: '2026-07-14',
            description: 'Maintenance 5j · NON réductrice.',
        );

        // Vol simple
        $this->createUnavailability(
            vehicle: $vehicles['EH-008-HH'],
            type: UnavailabilityType::Theft,
            startDate: '2026-04-12',
            endDate: '2026-04-25',
            description: 'Vol simple 14j 2026 · NON réductrice.',
        );

        // Suspension CI 59j à cheval 2026 non bissextile
        $this->createUnavailability(
            vehicle: $vehicles['EF-006-FF'],
            type: UnavailabilityType::CiSuspension,
            startDate: '2026-02-01',
            endDate: '2026-03-31',
            description: 'Suspension CI 59j 2026 (28+31) non bissextile.',
        );

        // Fourrière publique sur véhicule électrique (test calcul · 0 € malgré indispo)
        $this->createUnavailability(
            vehicle: $vehicles['FD-030-DD'],
            type: UnavailabilityType::PoundPublic,
            startDate: '2026-06-05',
            endDate: '2026-06-20',
            description: 'Réductrice sur élec · 0€ malgré indispo (exonération R-XXXX-016).',
        );

        // CT contrôle technique
        $this->createUnavailability(
            vehicle: $vehicles['FA-027-AA'],
            type: UnavailabilityType::TechnicalInspection,
            startDate: '2026-09-15',
            endDate: '2026-09-16',
            description: 'CT 2026.',
        );

        // Accident réparation sur véhicule E85 (vérifier que abat applique sans interférence)
        $this->createUnavailability(
            vehicle: $vehicles['FU-047-UU'],
            type: UnavailabilityType::AccidentRepair,
            startDate: '2026-04-08',
            endDate: '2026-04-22',
            description: 'Réparation accident 15j · NON réductrice + E85 abat actif.',
        );
    }

    private function createUnavailability(
        Vehicle $vehicle,
        UnavailabilityType $type,
        string $startDate,
        string $endDate,
        ?string $description = null,
    ): void {
        Unavailability::create([
            'vehicle_id' => $vehicle->id,
            'type' => $type,
            'has_fiscal_impact' => $type->isFiscallyReductive(),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'description' => $description,
        ]);
    }

    /**
     * Plan de contrats 2024 conçu pour produire une démo parlante :
     *
     * - Couples sous le seuil LCD 30 j → exonération (ex: COR × 308 = 25 j)
     * - Couples au-dessus → taxes prorata (ex: ACM × 308 = 90 j)
     * - Véhicule électrique utilisé → 0 € CO₂, 0 € polluants
     * - Véhicule handicap utilisé → 0 € tout
     * - Rotations sur plusieurs véhicules pour une même entreprise
     *
     * @return list<array{plate:string,company:string,from:string,to:string}>
     */
    private function buildContractPlan2024(): array
    {
        return [
            // --- Peugeot 308 (essence Euro 6, WLTP 100 g/km) ---
            ['plate' => 'EA-001-AA', 'company' => 'ACM', 'from' => '2024-01-08', 'to' => '2024-02-29'], // 53 j > 30 → taxé
            ['plate' => 'EA-001-AA', 'company' => 'BTP', 'from' => '2024-03-04', 'to' => '2024-03-18'], // 15 j ≤ 30 → LCD
            ['plate' => 'EA-001-AA', 'company' => 'COR', 'from' => '2024-04-02', 'to' => '2024-04-21'], // 20 j ≤ 30 → LCD
            ['plate' => 'EA-001-AA', 'company' => 'ACM', 'from' => '2024-05-02', 'to' => '2024-06-18'], // s'ajoute aux 53 → 100 j cumulé
            ['plate' => 'EA-001-AA', 'company' => 'DRS', 'from' => '2024-07-01', 'to' => '2024-07-05'], // 5 j ≤ 30 → LCD
            ['plate' => 'EA-001-AA', 'company' => 'ECO', 'from' => '2024-09-09', 'to' => '2024-11-15'], // 68 j > 30

            // --- Renault Trafic (Diesel Euro 6 - taxe polluants 500 €) ---
            ['plate' => 'EB-002-BB', 'company' => 'BTP', 'from' => '2024-01-15', 'to' => '2024-04-30'], // 107 j
            ['plate' => 'EB-002-BB', 'company' => 'DRS', 'from' => '2024-05-06', 'to' => '2024-05-20'], // 15 j ≤ 30 → LCD
            ['plate' => 'EB-002-BB', 'company' => 'ACM', 'from' => '2024-06-03', 'to' => '2024-06-28'], // 26 j ≤ 30 → LCD
            ['plate' => 'EB-002-BB', 'company' => 'BTP', 'from' => '2024-09-02', 'to' => '2024-11-29'], // s'ajoute aux 107

            // --- Tesla Model 3 (électrique → exonération CO₂ + cat E = 0 €) ---
            ['plate' => 'EC-003-CC', 'company' => 'ECO', 'from' => '2024-01-02', 'to' => '2024-04-12'], // 102 j, 0 € quand même
            ['plate' => 'EC-003-CC', 'company' => 'COR', 'from' => '2024-04-22', 'to' => '2024-05-03'], // 12 j ≤ 30 → LCD (0 € de toute façon)
            ['plate' => 'EC-003-CC', 'company' => 'ACM', 'from' => '2024-05-06', 'to' => '2024-08-30'],
            ['plate' => 'EC-003-CC', 'company' => 'ECO', 'from' => '2024-09-02', 'to' => '2024-12-13'],

            // --- Peugeot 207 (NEDC essence, vieux) ---
            ['plate' => 'ED-004-DD', 'company' => 'DRS', 'from' => '2024-02-01', 'to' => '2024-05-31'], // >30
            ['plate' => 'ED-004-DD', 'company' => 'BTP', 'from' => '2024-06-10', 'to' => '2024-07-02'], // 23 j ≤ 30 → LCD
            ['plate' => 'ED-004-DD', 'company' => 'DRS', 'from' => '2024-09-02', 'to' => '2024-12-20'],

            // --- Renault 21 (PA 7 CV - taxe CO₂ lourde : 15 000 €/an) ---
            // Multi-VFC : bascules 2024-03-15 (PA 8→7) et 2024-09-10 (PA 7→8).
            // Le contrat COR chevauche la 1ʳᵉ bascule, le contrat ECO la 2ᵉ —
            // exerce le segmenteur PA pour le calcul taxe CO₂.
            ['plate' => 'EE-005-EE', 'company' => 'COR', 'from' => '2024-03-04', 'to' => '2024-03-28'], // 25 j ≤ 30, à cheval bascule 03-15
            ['plate' => 'EE-005-EE', 'company' => 'ACM', 'from' => '2024-07-01', 'to' => '2024-07-26'], // 26 j ≤ 30 → LCD
            ['plate' => 'EE-005-EE', 'company' => 'ECO', 'from' => '2024-09-02', 'to' => '2024-10-31'], // 60 j, à cheval bascule 09-10

            // --- Toyota Yaris hybride essence Euro 6 WLTP 95 g/km ---
            ['plate' => 'EF-006-FF', 'company' => 'ACM', 'from' => '2024-01-02', 'to' => '2024-03-29'],
            ['plate' => 'EF-006-FF', 'company' => 'ECO', 'from' => '2024-04-15', 'to' => '2024-07-31'],
            ['plate' => 'EF-006-FF', 'company' => 'COR', 'from' => '2024-08-12', 'to' => '2024-08-30'], // 19 j ≤ 30 → LCD
            ['plate' => 'EF-006-FF', 'company' => 'BTP', 'from' => '2024-10-07', 'to' => '2024-12-15'],

            // --- BMW Série 5 Diesel ---
            ['plate' => 'EG-007-GG', 'company' => 'ECO', 'from' => '2024-02-05', 'to' => '2024-04-25'],
            ['plate' => 'EG-007-GG', 'company' => 'DRS', 'from' => '2024-05-06', 'to' => '2024-05-25'], // 20 j ≤ 30 → LCD
            ['plate' => 'EG-007-GG', 'company' => 'ACM', 'from' => '2024-06-10', 'to' => '2024-08-30'],
            ['plate' => 'EG-007-GG', 'company' => 'BTP', 'from' => '2024-10-14', 'to' => '2024-12-15'],

            // --- Peugeot Partner (utilitaire N1 transport pers.) ---
            // Multi-VFC : bascules 2024-04-01 (CO₂ 145→130) et 2024-09-01
            // (CO₂ 130→150). Le contrat COR démarre AU jour de la bascule
            // 04-01 (entièrement sous la nouvelle VFC). Le contrat BTP
            // 08-15 → 09-05 chevauche la bascule 09-01 — exerce le
            // segmenteur CO₂.
            ['plate' => 'EH-008-HH', 'company' => 'BTP', 'from' => '2024-01-08', 'to' => '2024-03-15'],
            ['plate' => 'EH-008-HH', 'company' => 'COR', 'from' => '2024-04-01', 'to' => '2024-04-26'], // 26 j ≤ 30 → LCD
            ['plate' => 'EH-008-HH', 'company' => 'DRS', 'from' => '2024-05-13', 'to' => '2024-07-31'],
            ['plate' => 'EH-008-HH', 'company' => 'BTP', 'from' => '2024-08-15', 'to' => '2024-09-05'], // 22 j ≤ 30, à cheval bascule 09-01
            ['plate' => 'EH-008-HH', 'company' => 'ACM', 'from' => '2024-09-09', 'to' => '2024-11-15'],

            // --- Ford Transit Custom Diesel Euro 6 ---
            ['plate' => 'EI-009-II', 'company' => 'ACM', 'from' => '2024-01-15', 'to' => '2024-04-30'],
            ['plate' => 'EI-009-II', 'company' => 'ECO', 'from' => '2024-05-13', 'to' => '2024-07-19'],
            ['plate' => 'EI-009-II', 'company' => 'BTP', 'from' => '2024-09-02', 'to' => '2024-09-27'], // 26 j ≤ 30 → LCD
            ['plate' => 'EI-009-II', 'company' => 'COR', 'from' => '2024-10-07', 'to' => '2024-12-13'],

            // --- Renault Kangoo handicap (exonération totale) ---
            ['plate' => 'EJ-010-JJ', 'company' => 'COR', 'from' => '2024-03-04', 'to' => '2024-05-31'],
            ['plate' => 'EJ-010-JJ', 'company' => 'DRS', 'from' => '2024-06-17', 'to' => '2024-09-30'],
            ['plate' => 'EJ-010-JJ', 'company' => 'ECO', 'from' => '2024-11-04', 'to' => '2024-12-15'],

            // --- Renault Mégane (multi-VFC : 102 g/km jusqu'au 15/06,
            // 145 g/km à partir du 16/06). Trois contrats répartis :
            // un avant la bascule, un à cheval sur la bascule, un après.
            // Permet d'observer le segmenteur fiscal sur la fiche.
            ['plate' => 'EL-012-LL', 'company' => 'ACM', 'from' => '2024-02-05', 'to' => '2024-04-30'],
            ['plate' => 'EL-012-LL', 'company' => 'BTP', 'from' => '2024-05-13', 'to' => '2024-07-26'],
            ['plate' => 'EL-012-LL', 'company' => 'ECO', 'from' => '2024-09-09', 'to' => '2024-11-29'],

            // === Nouveaux véhicules 2024 · diversifie le test
            // (EM, EN, EO, EP, EQ ont des LCD particuliers + clusters plus bas
            // qui les utilisent, donc on laisse leur LLD pour 2025+)
            ['plate' => 'EM-013-MM', 'company' => 'IDF', 'from' => '2024-04-01', 'to' => '2024-12-31'],
            ['plate' => 'EN-014-NN', 'company' => 'HEX', 'from' => '2024-04-15', 'to' => '2024-11-30'],
            ['plate' => 'EO-015-OO', 'company' => 'LOG', 'from' => '2024-05-01', 'to' => '2024-12-31'],
            ['plate' => 'EP-016-PP', 'company' => 'PRO', 'from' => '2024-06-01', 'to' => '2024-10-31'],
            // EQ-017-QQ a un cluster 4 LCD juillet-octobre → LLD jan-mai uniquement
            ['plate' => 'EQ-017-QQ', 'company' => 'EOL', 'from' => '2024-01-15', 'to' => '2024-06-30'],
            ['plate' => 'ET-020-TT', 'company' => 'NOV', 'from' => '2024-04-01', 'to' => '2024-12-31'],
            ['plate' => 'FA-027-AA', 'company' => 'BAT', 'from' => '2024-03-12', 'to' => '2024-11-30'],
            ['plate' => 'FB-028-BB', 'company' => 'COB', 'from' => '2024-01-08', 'to' => '2024-12-20'],
            ['plate' => 'FD-030-DD', 'company' => 'TUR', 'from' => '2024-05-01', 'to' => '2024-12-31'], // électrique
            ['plate' => 'FE-031-EE', 'company' => 'MAG', 'from' => '2024-06-15', 'to' => '2024-12-31'], // électrique
            ['plate' => 'FU-047-UU', 'company' => 'BTP', 'from' => '2024-04-10', 'to' => '2024-11-15'], // E85 130g
            ['plate' => 'FV-048-VV', 'company' => 'ACM', 'from' => '2024-02-01', 'to' => '2024-12-15'], // E85 100g
            ['plate' => 'FZ-052-ZZ', 'company' => 'COR', 'from' => '2024-03-04', 'to' => '2024-12-31'], // handicap zeroing
            ['plate' => 'GA-053-AA', 'company' => 'EOL', 'from' => '2024-04-22', 'to' => '2024-12-31'], // hybride éligible R-2024-017

            // === Sorties de flotte · contrats avant exit_date ===
            ['plate' => 'GG-059-GG', 'company' => 'ACM', 'from' => '2024-02-15', 'to' => '2024-07-31'], // sortie 2024-08-15
            ['plate' => 'GH-060-HH', 'company' => 'NOV', 'from' => '2024-05-01', 'to' => '2024-12-31'], // sortie 2025-07-20

            // === Cas LCD particuliers 2024 (placés AVANT/APRÈS les LLD pour éviter overlap) ===
            // LCD 30j exact sur EM avant son LLD (qui démarre 04-01)
            ['plate' => 'EM-013-MM', 'company' => 'COR', 'from' => '2024-01-04', 'to' => '2024-02-02', 'notes' => 'LCD 30j exact'],
            // LCD février 2024 bissextile = 29 j sur EN avant son LLD (qui démarre 04-15)
            ['plate' => 'EN-014-NN', 'company' => 'PRO', 'from' => '2024-02-01', 'to' => '2024-02-29', 'notes' => 'LCD mois civil entier 29j bissextile'],
            // LCD mars 2024 31j mois entier sur EO avant son LLD (qui démarre 05-01)
            ['plate' => 'EO-015-OO', 'company' => 'MAG', 'from' => '2024-03-01', 'to' => '2024-03-31', 'notes' => 'LCD mois entier 31j'],
            // LCD 31j non mois entier · ER-018-RR (libre toute l'année 2024)
            ['plate' => 'ER-018-RR', 'company' => 'IDF', 'from' => '2024-04-05', 'to' => '2024-05-05', 'notes' => 'LCD 31j non mois entier · taxable'],

            // === Cluster de risque · 4 LCD consécutifs même entreprise sur même véhicule ===
            ['plate' => 'EQ-017-QQ', 'company' => 'HEX', 'from' => '2024-07-01', 'to' => '2024-07-25', 'notes' => 'Cluster LCD 1/4'],
            ['plate' => 'EQ-017-QQ', 'company' => 'HEX', 'from' => '2024-08-02', 'to' => '2024-08-28', 'notes' => 'Cluster LCD 2/4'],
            ['plate' => 'EQ-017-QQ', 'company' => 'HEX', 'from' => '2024-09-02', 'to' => '2024-09-30', 'notes' => 'Cluster LCD 3/4'],
            ['plate' => 'EQ-017-QQ', 'company' => 'HEX', 'from' => '2024-10-05', 'to' => '2024-10-29', 'notes' => 'Cluster LCD 4/4'],
        ];
    }

    /**
     * Contrats 2025 · couvre scission ADR-0022 01/03/2025 (R-2025-004/004-bis
     * rédactionnel) + cross-année 2024/2025 + nouveaux véhicules + clusters.
     *
     * @return list<array{plate:string,company:string,from:string,to:string,notes?:string,ref?:string}>
     */
    private function buildContractPlan2025(): array
    {
        return [
            // === LLD plein année 2025 sur véhicules diversifiés ===
            ['plate' => 'EA-001-AA', 'company' => 'IDF', 'from' => '2025-01-01', 'to' => '2025-12-31'], // 308 WLTP 100g
            ['plate' => 'EB-002-BB', 'company' => 'BTP', 'from' => '2025-01-15', 'to' => '2025-12-15'],
            ['plate' => 'EC-003-CC', 'company' => 'ECO', 'from' => '2025-01-01', 'to' => '2025-12-31'], // Tesla élec
            ['plate' => 'EF-006-FF', 'company' => 'ACM', 'from' => '2025-02-01', 'to' => '2025-12-31'],
            ['plate' => 'EH-008-HH', 'company' => 'BAT', 'from' => '2025-03-04', 'to' => '2025-11-30'],
            ['plate' => 'EJ-010-JJ', 'company' => 'COR', 'from' => '2025-01-08', 'to' => '2025-12-31'], // handicap
            ['plate' => 'EL-012-LL', 'company' => 'HEX', 'from' => '2025-02-15', 'to' => '2025-11-30'],
            ['plate' => 'EM-013-MM', 'company' => 'LOG', 'from' => '2025-01-01', 'to' => '2025-12-31'],
            ['plate' => 'EQ-017-QQ', 'company' => 'EOL', 'from' => '2025-02-01', 'to' => '2025-12-31'],
            ['plate' => 'ET-020-TT', 'company' => 'NOV', 'from' => '2025-01-15', 'to' => '2025-11-15'],

            // === Cross-année 2024 → 2025 (LCD chevauchant qualifie/dis-qualifie) ===
            // LCD 27 j (déc 2024 → jan 2025) · ≤ 30 j → exempt en 2025
            ['plate' => 'ER-018-RR', 'company' => 'PRO', 'from' => '2024-12-20', 'to' => '2025-01-15', 'notes' => 'LCD à cheval 27j ≤ 30j'],
            // LCD 32 j cross-année (NON exempt R-2025-021)
            ['plate' => 'ES-019-SS', 'company' => 'TUR', 'from' => '2024-12-15', 'to' => '2025-01-15', 'notes' => 'LCD 32j chevauchant · taxable'],
            // LLD plein cross-année
            ['plate' => 'EU-021-UU', 'company' => 'MAG', 'from' => '2024-11-01', 'to' => '2025-04-30'],

            // === Contrats à cheval sur scission ADR-0022 01/03/2025 ===
            // Cas 1 · LLD plein année (chevauche scission rédactionnelle R-2025-004)
            ['plate' => 'EV-022-VV', 'company' => 'IDF', 'from' => '2025-01-01', 'to' => '2025-12-31', 'notes' => 'À cheval scission 01/03/2025'],
            // Cas 2 · LCD à cheval (15/02 → 15/03 = 29j ≤ 30, exempt par durée)
            ['plate' => 'EW-023-WW', 'company' => 'COB', 'from' => '2025-02-15', 'to' => '2025-03-15', 'notes' => 'LCD 29j à cheval scission'],
            // Cas 3 · LCD long 32j à cheval scission (NON exempt)
            ['plate' => 'EX-024-XX', 'company' => 'COR', 'from' => '2025-02-15', 'to' => '2025-03-18', 'notes' => 'LCD 32j à cheval scission · taxable'],

            // === E85 actif en 2025 (R-2025-023) sur véhicules E85 ===
            ['plate' => 'FU-047-UU', 'company' => 'BTP', 'from' => '2025-01-01', 'to' => '2025-12-31', 'notes' => 'E85 130g WLTP · abattement actif 2025'],
            ['plate' => 'FV-048-VV', 'company' => 'ACM', 'from' => '2025-02-01', 'to' => '2025-12-15', 'notes' => 'E85 100g WLTP · abattement 100→60'],
            ['plate' => 'FW-049-WW', 'company' => 'EOL', 'from' => '2025-03-04', 'to' => '2025-10-31', 'notes' => 'E85 251g · perte abattement plafond 250'],
            ['plate' => 'FX-050-XX', 'company' => 'HEX', 'from' => '2025-01-15', 'to' => '2025-12-31', 'notes' => 'E85 PA 12 CV · abattement -2 CV'],
            ['plate' => 'FY-051-YY', 'company' => 'IDF', 'from' => '2025-02-01', 'to' => '2025-11-30', 'notes' => 'E85 PA 13 CV · perte abattement plafond'],

            // === Multi-VFC · activation E85 mid-2025 sur GK-063-KK ===
            ['plate' => 'GK-063-KK', 'company' => 'DRS', 'from' => '2025-01-01', 'to' => '2025-12-31', 'notes' => 'VFC bascule E85 au 01/07/2025'],
            // === Multi-VFC · changement PollutantCategory mid-2025 sur GL-064-LL ===
            ['plate' => 'GL-064-LL', 'company' => 'LOG', 'from' => '2025-01-01', 'to' => '2025-12-31', 'notes' => 'VFC Cat1→MostPolluting 01/09/2025'],

            // === Indispos compatibles (cf. seedUnavailabilities) sur ces véhicules ===
            ['plate' => 'EY-025-YY', 'company' => 'MAG', 'from' => '2025-01-01', 'to' => '2025-09-30'],
            ['plate' => 'EZ-026-ZZ', 'company' => 'PRO', 'from' => '2025-02-15', 'to' => '2025-12-31'],
            ['plate' => 'FB-028-BB', 'company' => 'BAT', 'from' => '2025-04-01', 'to' => '2025-12-31'],
            ['plate' => 'FC-029-CC', 'company' => 'COB', 'from' => '2025-03-15', 'to' => '2025-11-15'],
            ['plate' => 'FH-034-HH', 'company' => 'TUR', 'from' => '2025-02-20', 'to' => '2025-10-31'],
            ['plate' => 'FJ-036-JJ', 'company' => 'NOV', 'from' => '2025-05-01', 'to' => '2025-12-31'],
            ['plate' => 'FL-038-LL', 'company' => 'HEX', 'from' => '2025-01-15', 'to' => '2025-12-31'],
            ['plate' => 'FM-039-MM', 'company' => 'EOL', 'from' => '2025-03-01', 'to' => '2025-11-30'],
            ['plate' => 'FP-042-PP', 'company' => 'ACM', 'from' => '2025-04-15', 'to' => '2025-12-31'],
            ['plate' => 'FT-046-TT', 'company' => 'COR', 'from' => '2025-06-01', 'to' => '2025-12-31'],

            // === Sortie de flotte 2025 · GH-060-HH (exit_date 2025-07-20) + GJ-062-JJ ===
            ['plate' => 'GH-060-HH', 'company' => 'NOV', 'from' => '2025-01-01', 'to' => '2025-07-15'],
            ['plate' => 'GJ-062-JJ', 'company' => 'PRO', 'from' => '2025-01-15', 'to' => '2025-10-25'],

            // === Plusieurs entreprises utilisatrices successives sur même véhicule ===
            ['plate' => 'EG-007-GG', 'company' => 'ACM', 'from' => '2025-01-08', 'to' => '2025-04-30'],
            ['plate' => 'EG-007-GG', 'company' => 'BTP', 'from' => '2025-05-15', 'to' => '2025-08-15'],
            ['plate' => 'EG-007-GG', 'company' => 'IDF', 'from' => '2025-09-01', 'to' => '2025-12-31'],
        ];
    }

    /**
     * Contrats 2026 · couvre scission ADR-0022 01/03/2026 (R-2026-014/014-bis
     * MATÉRIEL +30 % polluants) + scission 01/09/2026 rédactionnel + clusters.
     *
     * @return list<array{plate:string,company:string,from:string,to:string,notes?:string,ref?:string}>
     */
    private function buildContractPlan2026(): array
    {
        return [
            // === LLD plein 2026 · test moyenne pondérée polluants (Cat1 = 125,15 €) ===
            ['plate' => 'EA-001-AA', 'company' => 'IDF', 'from' => '2026-01-01', 'to' => '2026-12-31', 'notes' => 'LLD full-year · Cat1 pondéré 125,15 €'],
            ['plate' => 'EB-002-BB', 'company' => 'BTP', 'from' => '2026-01-15', 'to' => '2026-12-15', 'notes' => 'MostPolluting full-year · pondéré 625,75 €'],
            ['plate' => 'EC-003-CC', 'company' => 'ECO', 'from' => '2026-01-01', 'to' => '2026-12-31'], // Tesla élec
            ['plate' => 'EF-006-FF', 'company' => 'ACM', 'from' => '2026-02-01', 'to' => '2026-12-31'],
            ['plate' => 'EJ-010-JJ', 'company' => 'COR', 'from' => '2026-01-08', 'to' => '2026-12-31'], // handicap

            // === Contrats à cheval scission MATÉRIELLE 01/03/2026 (polluants +30 %) ===
            // LLD couvrant la scission · doit appliquer la moyenne pondérée
            ['plate' => 'EM-013-MM', 'company' => 'HEX', 'from' => '2026-01-15', 'to' => '2026-04-30', 'notes' => 'À cheval scission polluants 01/03/2026'],
            ['plate' => 'EN-014-NN', 'company' => 'IDF', 'from' => '2026-02-01', 'to' => '2026-04-30', 'notes' => 'À cheval polluants Cat1'],
            ['plate' => 'EQ-017-QQ', 'company' => 'EOL', 'from' => '2026-02-15', 'to' => '2026-05-15', 'notes' => 'À cheval polluants'],
            // LCD 29 j à cheval (exempt par durée)
            ['plate' => 'EO-015-OO', 'company' => 'LOG', 'from' => '2026-02-20', 'to' => '2026-03-20', 'notes' => 'LCD 29j à cheval scission'],
            // LCD 32 j à cheval (taxable)
            ['plate' => 'ES-019-SS', 'company' => 'MAG', 'from' => '2026-02-15', 'to' => '2026-03-18', 'notes' => 'LCD 32j à cheval scission · taxable'],

            // === Contrats à cheval scission RÉDACTIONNELLE 01/09/2026 ===
            ['plate' => 'ET-020-TT', 'company' => 'NOV', 'from' => '2026-07-01', 'to' => '2026-10-31', 'notes' => 'À cheval Ordo 2025-1247 01/09/2026'],
            ['plate' => 'EU-021-UU', 'company' => 'COB', 'from' => '2026-08-15', 'to' => '2026-11-30', 'notes' => 'À cheval rédactionnel polluants'],

            // === IDF Consulting · test majoration carte grise LF 2026 art. 60 ===
            ['plate' => 'EV-022-VV', 'company' => 'IDF', 'from' => '2026-04-01', 'to' => '2026-12-31', 'notes' => 'IDF post-LF 2026 art. 60'],

            // === E85 actif 2026 (R-2026-023 reconduit) ===
            ['plate' => 'FU-047-UU', 'company' => 'BTP', 'from' => '2026-01-01', 'to' => '2026-12-31', 'notes' => 'E85 130g WLTP · gain accru 2026 (barème durci)'],
            ['plate' => 'FV-048-VV', 'company' => 'ACM', 'from' => '2026-02-01', 'to' => '2026-12-15', 'notes' => 'E85 100g WLTP · abattement 2026 = 132 €'],
            ['plate' => 'FX-050-XX', 'company' => 'HEX', 'from' => '2026-01-15', 'to' => '2026-12-31', 'notes' => 'E85 PA 12 CV reconduit'],

            // === Sortie de flotte 2026 · GI-061-II (exit 2026-04-30) ===
            ['plate' => 'GI-061-II', 'company' => 'TUR', 'from' => '2026-01-15', 'to' => '2026-04-25'],

            // === LCD divers 2026 ===
            // LCD février 2026 = 28 j (non bissextile · exempt par durée)
            ['plate' => 'EP-016-PP', 'company' => 'PRO', 'from' => '2026-02-01', 'to' => '2026-02-28', 'notes' => 'LCD février 28j non bissextile'],
            // LCD mars 2026 = 31 j mois entier (exempt)
            ['plate' => 'ER-018-RR', 'company' => 'IDF', 'from' => '2026-03-01', 'to' => '2026-03-31', 'notes' => 'LCD mois entier mars 2026'],
            // LCD 30 j exact en mai 2026
            ['plate' => 'EW-023-WW', 'company' => 'COR', 'from' => '2026-05-01', 'to' => '2026-05-30', 'notes' => 'LCD 30j exact'],

            // === Cluster risque LCD 2026 (4 consécutifs même entreprise) ===
            ['plate' => 'EX-024-XX', 'company' => 'HEX', 'from' => '2026-07-01', 'to' => '2026-07-25'],
            ['plate' => 'EX-024-XX', 'company' => 'HEX', 'from' => '2026-08-02', 'to' => '2026-08-28'],
            ['plate' => 'EX-024-XX', 'company' => 'HEX', 'from' => '2026-09-02', 'to' => '2026-09-30'],
            ['plate' => 'EX-024-XX', 'company' => 'HEX', 'from' => '2026-10-05', 'to' => '2026-10-29'],

            // === Multi-affectation · même véhicule, plusieurs entreprises 2026 ===
            ['plate' => 'EY-025-YY', 'company' => 'ACM', 'from' => '2026-01-01', 'to' => '2026-04-15'],
            ['plate' => 'EY-025-YY', 'company' => 'BTP', 'from' => '2026-05-01', 'to' => '2026-08-15'],
            ['plate' => 'EY-025-YY', 'company' => 'IDF', 'from' => '2026-09-01', 'to' => '2026-12-31'],

            // === Cross-année 2025 → 2026 (LCD chevauchant) ===
            // LCD 27 j (déc 2025 → jan 2026) · ≤ 30 j → exempt en 2026
            ['plate' => 'EZ-026-ZZ', 'company' => 'EOL', 'from' => '2025-12-20', 'to' => '2026-01-15', 'notes' => 'LCD cross 2025/2026 · ≤30j exempt'],
            // LLD plein cross-année
            ['plate' => 'FA-027-AA', 'company' => 'BAT', 'from' => '2025-11-15', 'to' => '2026-05-15'],

            // === Autres LLD 2026 standards ===
            ['plate' => 'FB-028-BB', 'company' => 'COB', 'from' => '2026-01-08', 'to' => '2026-12-31'],
            ['plate' => 'FD-030-DD', 'company' => 'TUR', 'from' => '2026-01-15', 'to' => '2026-12-31'], // élec
            ['plate' => 'FE-031-EE', 'company' => 'MAG', 'from' => '2026-02-01', 'to' => '2026-12-31'], // élec
            ['plate' => 'FG-033-GG', 'company' => 'LOG', 'from' => '2026-03-01', 'to' => '2026-12-31'], // Master ZE
            ['plate' => 'FZ-052-ZZ', 'company' => 'COR', 'from' => '2026-01-01', 'to' => '2026-12-31'], // handicap zeroing
            ['plate' => 'FC-029-CC', 'company' => 'NOV', 'from' => '2026-02-15', 'to' => '2026-11-30'],
            ['plate' => 'FH-034-HH', 'company' => 'PRO', 'from' => '2026-03-04', 'to' => '2026-10-31'],
            ['plate' => 'FJ-036-JJ', 'company' => 'HEX', 'from' => '2026-04-01', 'to' => '2026-12-31'],
            ['plate' => 'FL-038-LL', 'company' => 'EOL', 'from' => '2026-01-15', 'to' => '2026-12-31'],
            ['plate' => 'FP-042-PP', 'company' => 'IDF', 'from' => '2026-05-01', 'to' => '2026-12-31'],
            ['plate' => 'GA-053-AA', 'company' => 'ACM', 'from' => '2026-01-01', 'to' => '2026-12-31'], // Prius (R-2024-017 disparu 2025+)
        ];
    }
}
