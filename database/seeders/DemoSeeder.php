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
use App\Models\Company;
use App\Models\Contract;
use App\Models\Driver;
use App\Models\Unavailability;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
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
        DB::transaction(function (): void {
            $companies = $this->seedCompanies();
            $vehicles = $this->seedVehicles();
            $this->seedContracts2024($vehicles, $companies);
            $this->seedUnavailabilities2024($vehicles);
            $this->seedDrivers($companies);
        });
    }

    /**
     * Phase 06 V1.2 - 8 conducteurs démo couvrant les cas d'usage Driver↔Company N:N :
     *   - 5 simples (1 par entreprise, actifs depuis 2024)
     *   - 1 multi-companies (Sophie : ACM + BTP)
     *   - 1 sorti d'une entreprise après 2026 (Pierre : sorti d'ACM le 2026-04-30)
     *   - 1 multi-companies (Aurélie : ECO + DRS)
     *
     * @param  array<string, Company>  $companies
     */
    private function seedDrivers(array $companies): void
    {
        $specs = [
            ['first' => 'Marc', 'last' => 'Dubois', 'memberships' => [['code' => 'ACM', 'joined' => '2024-01-01', 'left' => null]]],
            ['first' => 'Sophie', 'last' => 'Martin', 'memberships' => [
                ['code' => 'ACM', 'joined' => '2024-03-15', 'left' => null],
                ['code' => 'BTP', 'joined' => '2025-01-01', 'left' => null],
            ]],
            ['first' => 'Pierre', 'last' => 'Lefebvre', 'memberships' => [['code' => 'ACM', 'joined' => '2024-01-15', 'left' => '2026-04-30']]],
            ['first' => 'Julie', 'last' => 'Bernard', 'memberships' => [['code' => 'COR', 'joined' => '2024-02-01', 'left' => null]]],
            ['first' => 'Thomas', 'last' => 'Petit', 'memberships' => [['code' => 'BTP', 'joined' => '2024-01-10', 'left' => null]]],
            ['first' => 'Camille', 'last' => 'Roux', 'memberships' => [['code' => 'DRS', 'joined' => '2024-04-01', 'left' => null]]],
            ['first' => 'Nicolas', 'last' => 'Moreau', 'memberships' => [['code' => 'ECO', 'joined' => '2024-01-20', 'left' => null]]],
            ['first' => 'Aurélie', 'last' => 'Simon', 'memberships' => [
                ['code' => 'ECO', 'joined' => '2024-06-01', 'left' => null],
                ['code' => 'DRS', 'joined' => '2025-03-01', 'left' => null],
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
     * @return array<string, Company>
     */
    private function seedCompanies(): array
    {
        $specs = [
            ['code' => 'ACM', 'name' => 'ACME Logistique', 'siren' => '812345678', 'color' => CompanyColor::Indigo, 'city' => 'Lyon'],
            ['code' => 'BTP', 'name' => 'BTP Confort', 'siren' => '813456789', 'color' => CompanyColor::Amber, 'city' => 'Grenoble'],
            ['code' => 'COR', 'name' => 'Corsica Events', 'siren' => '814567890', 'color' => CompanyColor::Emerald, 'city' => 'Ajaccio'],
            ['code' => 'DRS', 'name' => 'Dauphiné Services', 'siren' => '815678901', 'color' => CompanyColor::Rose, 'city' => 'Valence'],
            ['code' => 'ECO', 'name' => 'Éco Distribution', 'siren' => '816789012', 'color' => CompanyColor::Violet, 'city' => 'Saint-Étienne'],
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
            'Ajaccio' => '20000',
            'Valence' => '26000',
            'Saint-Étienne' => '42000',
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
            // Renault 21 essence 7 CV - PA (trop vieux pour NEDC)
            [
                'plate' => 'EE-005-EE', 'brand' => 'Renault', 'model' => '21 Nevada',
                'regFrench' => '2002-05-15', 'regOrigin' => '2002-05-15', 'econ' => '2002-05-15',
                'user' => VehicleUserType::PassengerCar, 'body' => BodyType::StationWagon, 'cat' => ReceptionCategory::M1, 'seats' => 5,
                'energy' => EnergySource::Gasoline, 'euro' => EuroStandard::Euro3,
                'pollutant' => PollutantCategory::MostPolluting,
                'method' => HomologationMethod::Pa, 'pa' => 7, 'kerb' => 1200,
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
            // Peugeot Partner camionnette Diesel Euro 6 - N1 transport personnes
            [
                'plate' => 'EH-008-HH', 'brand' => 'Peugeot', 'model' => 'Partner 2 rangs',
                'regFrench' => '2023-03-05', 'regOrigin' => '2023-03-05', 'econ' => '2023-03-05',
                'user' => VehicleUserType::CommercialVehicle, 'body' => BodyType::LightTruck, 'cat' => ReceptionCategory::N1, 'seats' => 5,
                'energy' => EnergySource::Diesel, 'euro' => EuroStandard::Euro6d,
                'pollutant' => PollutantCategory::MostPolluting,
                'method' => HomologationMethod::Wltp, 'co2Wltp' => 145, 'pa' => 7, 'kerb' => 1500,
                'n1PassengerTransport' => true,
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
                'n1_passenger_transport' => $spec['n1PassengerTransport'] ?? false,
                'n1_removable_second_row_seat' => false,
                'm1_special_use' => false,
                'n1_ski_lift_use' => false,
                'change_reason' => FiscalCharacteristicsChangeReason::InitialCreation,
            ]);

            $created[$spec['plate']] = $vehicle;
        }

        return $created;
    }

    /**
     * Crée des contrats 2024 (refonte ADR-0014) à partir du plan
     * d'attribution. Une entrée du plan = un contrat couvrant la
     * plage `[from, to]`. Le `contract_type` est déduit de la durée
     * (≤ 30 j → `lcd`, sinon `lld`) pour cohérence visuelle ; le
     * moteur fiscal calcule l'exonération LCD à partir des dates,
     * pas du libellé.
     *
     * @param  array<string, Vehicle>  $vehicles
     * @param  array<string, Company>  $companies
     */
    private function seedContracts2024(array $vehicles, array $companies): void
    {
        // Nettoyage : on repart à blanc pour 2024 pour la démo.
        Contract::query()
            ->where('start_date', '<=', '2024-12-31')
            ->where('end_date', '>=', '2024-01-01')
            ->forceDelete();

        $plan = $this->buildContractPlan();
        foreach ($plan as $row) {
            $vehicle = $vehicles[$row['plate']] ?? null;
            $company = $companies[$row['company']] ?? null;
            if ($vehicle === null || $company === null) {
                continue;
            }

            $start = Carbon::parse($row['from']);
            $end = Carbon::parse($row['to']);
            $duration = $start->diffInDays($end) + 1;
            $type = $duration <= 30 ? ContractType::Lcd : ContractType::Lld;

            Contract::create([
                'vehicle_id' => $vehicle->id,
                'company_id' => $company->id,
                'driver_id' => null,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'contract_reference' => null,
                'contract_type' => $type,
                'notes' => null,
            ]);
        }
    }

    /**
     * Quelques indispos seedées pour exercer la grille ADR-0016 rev. 1.1
     * en démo : au moins une de chaque type réducteur + un cas non
     * réducteur. Les plages sont choisies pour ne pas chevaucher les
     * contrats du plan (cf. trigger MySQL anti-overlap, ADR-0014).
     *
     * @param  array<string, Vehicle>  $vehicles
     */
    private function seedUnavailabilities2024(array $vehicles): void
    {
        Unavailability::query()
            ->where('start_date', '<=', '2024-12-31')
            ->where(function ($q): void {
                $q->whereNull('end_date')->orWhere('end_date', '>=', '2024-01-01');
            })
            ->forceDelete();

        // EJ-010-JJ Kangoo TPMR - créneau libre 01-10 → 03-10/2024
        // (avant le 1er contrat COR du 03-04). Fourrière publique 8 j.
        $this->createUnavailability(
            vehicle: $vehicles['EJ-010-JJ'],
            type: UnavailabilityType::PoundPublic,
            startDate: '2024-02-12',
            endDate: '2024-02-19',
            description: 'Stationnement gênant signalé par la mairie.',
        );

        // EI-009-II Ford Transit - créneau libre 06-01 → 09-30 (entre BTP et ECO).
        // Interdiction de circuler post-sinistre 12 j.
        $this->createUnavailability(
            vehicle: $vehicles['EI-009-II'],
            type: UnavailabilityType::AccidentNoCirculation,
            startDate: '2024-07-08',
            endDate: '2024-07-19',
            description: 'Choc latéral, expertise + interdiction préfectorale.',
        );

        // EG-007-GG BMW Série 5 - créneau hors contrats. Suspension CI 25 j.
        $this->createUnavailability(
            vehicle: $vehicles['EG-007-GG'],
            type: UnavailabilityType::CiSuspension,
            startDate: '2024-08-05',
            endDate: '2024-08-29',
            description: 'Suspension administrative du certificat d\'immatriculation.',
        );

        // EH-008-HH Partner - maintenance courante 4 j (non réducteur).
        $this->createUnavailability(
            vehicle: $vehicles['EH-008-HH'],
            type: UnavailabilityType::Maintenance,
            startDate: '2024-10-21',
            endDate: '2024-10-24',
            description: 'Révision constructeur + remplacement pneus AV.',
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
    private function buildContractPlan(): array
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
            ['plate' => 'EE-005-EE', 'company' => 'COR', 'from' => '2024-03-04', 'to' => '2024-03-28'], // 25 j ≤ 30 → LCD
            ['plate' => 'EE-005-EE', 'company' => 'ACM', 'from' => '2024-07-01', 'to' => '2024-07-26'], // 26 j ≤ 30 → LCD

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
            ['plate' => 'EH-008-HH', 'company' => 'BTP', 'from' => '2024-01-08', 'to' => '2024-03-15'],
            ['plate' => 'EH-008-HH', 'company' => 'COR', 'from' => '2024-04-01', 'to' => '2024-04-26'], // 26 j ≤ 30 → LCD
            ['plate' => 'EH-008-HH', 'company' => 'DRS', 'from' => '2024-05-13', 'to' => '2024-07-31'],
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
        ];
    }
}
