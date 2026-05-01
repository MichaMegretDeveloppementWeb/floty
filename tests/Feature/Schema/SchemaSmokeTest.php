<?php

declare(strict_types=1);

namespace Tests\Feature\Schema;

use App\Enums\Company\CompanyColor;
use App\Enums\Contract\ContractType;
use App\Enums\Declaration\DeclarationStatus;
use App\Enums\Fiscal\RuleType;
use App\Enums\Unavailability\UnavailabilityType;
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
use App\Models\Declaration;
use App\Models\DeclarationPdf;
use App\Models\Driver;
use App\Models\FiscalRule;
use App\Models\Unavailability;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Smoke test du schéma global Floty V1 (phase 01.bis).
 *
 * Crée une chaîne complète d'entités : Company → Driver → Vehicle
 * → VehicleFiscalCharacteristics → Contract → Unavailability →
 * Declaration → DeclarationPdf → FiscalRule.
 *
 * Vérifie que :
 *   - chaque modèle se persiste avec son `$fillable`
 *   - les enums sont castés dans les deux sens
 *   - les relations remontent correctement (type + valeurs)
 *   - SoftDeletes fonctionne sur les entités concernées
 *   - le trigger anti-chevauchement de `vehicle_fiscal_characteristics`
 *     rejette bien une période qui chevauche l'existante
 */
final class SchemaSmokeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function full_domain_graph_persists_and_relates_correctly(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Renaud',
            'last_name' => 'Nicolas',
        ]);

        $company = Company::create([
            'legal_name' => 'ACME Industries',
            'short_code' => 'AC',
            'color' => CompanyColor::Indigo,
            'country' => 'FR',
            'is_active' => true,
        ]);

        $driver = Driver::create([
            'first_name' => 'Marie',
            'last_name' => 'Dupont',
        ]);
        $driver->companies()->attach($company->id, [
            'joined_at' => now()->toDateString(),
        ]);

        $vehicle = Vehicle::create([
            'license_plate' => 'EH-142-AZ',
            'brand' => 'Peugeot',
            'model' => '308',
            'first_french_registration_date' => '2022-06-15',
            'first_origin_registration_date' => '2022-06-15',
            'first_economic_use_date' => '2022-06-16',
            'acquisition_date' => '2022-06-16',
            'current_status' => VehicleStatus::Active,
        ]);

        $fiscalVersion = VehicleFiscalCharacteristics::create([
            'vehicle_id' => $vehicle->id,
            'effective_from' => '2022-06-16',
            'effective_to' => null,
            'reception_category' => ReceptionCategory::M1,
            'vehicle_user_type' => VehicleUserType::PassengerCar,
            'body_type' => BodyType::InteriorDriving,
            'seats_count' => 5,
            'energy_source' => EnergySource::Gasoline,
            'pollutant_category' => PollutantCategory::Category1,
            'homologation_method' => HomologationMethod::Wltp,
            'euro_standard' => EuroStandard::Euro6dIscFcm,
            'co2_wltp' => 118,
            'change_reason' => FiscalCharacteristicsChangeReason::InitialCreation,
        ]);

        $contract = Contract::create([
            'vehicle_id' => $vehicle->id,
            'company_id' => $company->id,
            'driver_id' => $driver->id,
            'start_date' => '2024-03-15',
            'end_date' => '2024-03-15',
            'contract_type' => ContractType::Lcd,
        ]);

        $unavailability = Unavailability::create([
            'vehicle_id' => $vehicle->id,
            'type' => UnavailabilityType::PoundPublic,
            'has_fiscal_impact' => true,
            'start_date' => '2024-06-01',
            'end_date' => '2024-06-10',
        ]);

        $declaration = Declaration::create([
            'company_id' => $company->id,
            'fiscal_year' => 2024,
            'status' => DeclarationStatus::Draft,
            'status_changed_at' => now(),
            'status_changed_by' => $user->id,
            'is_invalidated' => false,
        ]);

        $pdf = DeclarationPdf::create([
            'declaration_id' => $declaration->id,
            'pdf_path' => 'declarations/2024/1/v1-1714000000.pdf',
            'pdf_filename' => 'declaration_ACME_2024_v1.pdf',
            'pdf_size_bytes' => 45678,
            'pdf_sha256' => str_repeat('a', 64),
            'snapshot_json' => ['schema_version' => '1.0', 'fiscal_year' => 2024],
            'snapshot_sha256' => str_repeat('b', 64),
            'generated_at' => now(),
            'generated_by' => $user->id,
            'version_number' => 1,
        ]);

        $rule = FiscalRule::create([
            'rule_code' => 'R-2024-010',
            'name' => 'Tarification WLTP 2024',
            'description' => 'Barème progressif CO₂ WLTP 2024.',
            'fiscal_year' => 2024,
            'rule_type' => RuleType::Tariff,
            'taxes_concerned' => ['co2'],
            'applicability_start' => '2024-01-01',
            'legal_basis' => [['type' => 'CIBS', 'article' => 'L. 421-120']],
            'vehicle_characteristics_consumed' => ['co2_wltp'],
            'code_reference' => 'rules/2024/tarification/wltp.php',
            'display_order' => 10,
            'is_active' => true,
        ]);

        // --- Assertions sur les casts enum (round-trip base → modèle) ---
        $company->refresh();
        $this->assertInstanceOf(CompanyColor::class, $company->color);
        $this->assertSame(CompanyColor::Indigo, $company->color);

        $vehicle->refresh();
        $this->assertInstanceOf(VehicleStatus::class, $vehicle->current_status);
        $this->assertSame(VehicleStatus::Active, $vehicle->current_status);
        $this->assertInstanceOf(CarbonImmutable::class, $vehicle->acquisition_date);

        $fiscalVersion->refresh();
        $this->assertSame(EnergySource::Gasoline, $fiscalVersion->energy_source);
        $this->assertSame(EuroStandard::Euro6dIscFcm, $fiscalVersion->euro_standard);
        $this->assertSame(BodyType::InteriorDriving, $fiscalVersion->body_type);
        $this->assertTrue($fiscalVersion->isCurrent());

        $declaration->refresh();
        $this->assertSame(DeclarationStatus::Draft, $declaration->status);

        $rule->refresh();
        $this->assertSame(RuleType::Tariff, $rule->rule_type);
        $this->assertSame(['co2'], $rule->taxes_concerned);

        $pdf->refresh();
        // assertEquals : MySQL `JSON` ne garantit pas l'ordre des clés après
        // stockage, donc on vérifie l'équivalence structurelle.
        $this->assertEquals(
            ['schema_version' => '1.0', 'fiscal_year' => 2024],
            $pdf->snapshot_json,
        );

        // --- Relations ---
        $this->assertSame($company->id, $driver->fresh()->companies->first()->id);
        $this->assertSame($vehicle->id, $fiscalVersion->vehicle->id);
        $this->assertSame($vehicle->id, $contract->vehicle->id);
        $this->assertSame($company->id, $contract->company->id);
        $this->assertSame($driver->id, $contract->driver->id);
        $this->assertSame($vehicle->id, $unavailability->vehicle->id);
        $this->assertSame($company->id, $declaration->company->id);
        $this->assertSame($user->id, $declaration->statusChangedBy->id);
        $this->assertSame($declaration->id, $pdf->declaration->id);
        $this->assertSame($user->id, $pdf->generatedBy->id);

        // Relations inverses
        $this->assertCount(1, $company->fresh()->drivers);
        $this->assertCount(1, $vehicle->fresh()->fiscalCharacteristics);
        $this->assertCount(1, $vehicle->fresh()->contracts);
        $this->assertCount(1, $vehicle->fresh()->unavailabilities);
        $this->assertCount(1, $declaration->fresh()->pdfs);
        $this->assertCount(1, $user->fresh()->changedDeclarations);
        $this->assertCount(1, $user->fresh()->generatedPdfs);
    }

    #[Test]
    public function vfc_overlap_trigger_rejects_overlapping_period_on_same_vehicle(): void
    {
        $vehicle = Vehicle::create([
            'license_plate' => 'ZZ-111-ZZ',
            'brand' => 'Test',
            'model' => 'Demo',
            'first_french_registration_date' => '2024-01-01',
            'first_origin_registration_date' => '2024-01-01',
            'first_economic_use_date' => '2024-01-02',
            'acquisition_date' => '2024-01-02',
            'current_status' => VehicleStatus::Active,
        ]);

        $baseFields = [
            'vehicle_id' => $vehicle->id,
            'reception_category' => ReceptionCategory::M1,
            'vehicle_user_type' => VehicleUserType::PassengerCar,
            'body_type' => BodyType::InteriorDriving,
            'seats_count' => 5,
            'energy_source' => EnergySource::Gasoline,
            'pollutant_category' => PollutantCategory::Category1,
            'homologation_method' => HomologationMethod::Wltp,
            'co2_wltp' => 120,
            'change_reason' => FiscalCharacteristicsChangeReason::InitialCreation,
        ];

        VehicleFiscalCharacteristics::create([
            ...$baseFields,
            'effective_from' => '2024-01-01',
            'effective_to' => '2024-06-30',
        ]);

        $this->expectExceptionMessageMatches('/overlapping effective period/');

        VehicleFiscalCharacteristics::create([
            ...$baseFields,
            'effective_from' => '2024-04-01',
            'effective_to' => null,
        ]);
    }

    #[Test]
    public function contract_persists_with_casts_and_relations(): void
    {
        $vehicle = Vehicle::factory()->create();
        $company = Company::factory()->create();

        $contract = Contract::create([
            'vehicle_id' => $vehicle->id,
            'company_id' => $company->id,
            'driver_id' => null,
            'start_date' => '2024-03-01',
            'end_date' => '2024-03-15',
            'contract_reference' => 'REF-001',
            'contract_type' => ContractType::Lcd,
            'notes' => null,
        ]);

        $contract->refresh();

        $this->assertSame(ContractType::Lcd, $contract->contract_type);
        $this->assertInstanceOf(CarbonImmutable::class, $contract->start_date);
        $this->assertSame('2024-03-15', $contract->end_date->toDateString());
        $this->assertSame($vehicle->id, $contract->vehicle->id);
        $this->assertSame($company->id, $contract->company->id);

        $contract->delete();
        $this->assertSoftDeleted($contract);
    }

    #[Test]
    public function contract_overlap_trigger_rejects_overlapping_period_on_same_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create();
        $company = Company::factory()->create();

        Contract::create([
            'vehicle_id' => $vehicle->id,
            'company_id' => $company->id,
            'start_date' => '2024-03-01',
            'end_date' => '2024-03-15',
            'contract_type' => ContractType::Lcd,
        ]);

        $this->expectExceptionMessageMatches('/overlapping period/');

        Contract::create([
            'vehicle_id' => $vehicle->id,
            'company_id' => $company->id,
            'start_date' => '2024-03-10',
            'end_date' => '2024-03-25',
            'contract_type' => ContractType::Lcd,
        ]);
    }

    #[Test]
    public function soft_deleted_contract_does_not_block_overlap(): void
    {
        $vehicle = Vehicle::factory()->create();
        $company = Company::factory()->create();

        $first = Contract::create([
            'vehicle_id' => $vehicle->id,
            'company_id' => $company->id,
            'start_date' => '2024-03-01',
            'end_date' => '2024-03-15',
            'contract_type' => ContractType::Lcd,
        ]);
        $first->delete();

        // Le contrat soft-deleted ne doit plus bloquer un nouveau contrat
        // sur la même plage (le trigger filtre `deleted_at IS NULL`).
        $second = Contract::create([
            'vehicle_id' => $vehicle->id,
            'company_id' => $company->id,
            'start_date' => '2024-03-05',
            'end_date' => '2024-03-20',
            'contract_type' => ContractType::Lld,
        ]);

        $this->assertNotSame($first->id, $second->id);
    }

    #[Test]
    public function soft_deleted_vehicles_can_reuse_license_plate(): void
    {
        $first = Vehicle::create([
            'license_plate' => 'AA-001-AA',
            'brand' => 'Test',
            'model' => 'V1',
            'first_french_registration_date' => '2022-01-01',
            'first_origin_registration_date' => '2022-01-01',
            'first_economic_use_date' => '2022-01-02',
            'acquisition_date' => '2022-01-02',
            'current_status' => VehicleStatus::Active,
        ]);

        $first->delete();
        $this->assertSoftDeleted($first);

        // La même plaque doit pouvoir être réutilisée sur un nouveau véhicule
        // (UNIQUE filtré par `deleted_at IS NULL` — colonne générée `license_plate_active`).
        $second = Vehicle::create([
            'license_plate' => 'AA-001-AA',
            'brand' => 'Test',
            'model' => 'V2',
            'first_french_registration_date' => '2024-01-01',
            'first_origin_registration_date' => '2024-01-01',
            'first_economic_use_date' => '2024-01-02',
            'acquisition_date' => '2024-01-02',
            'current_status' => VehicleStatus::Active,
        ]);

        $this->assertNotSame($first->id, $second->id);
    }
}
