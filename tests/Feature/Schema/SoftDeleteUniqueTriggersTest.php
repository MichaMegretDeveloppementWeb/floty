<?php

declare(strict_types=1);

namespace Tests\Feature\Schema;

use App\Enums\Company\CompanyColor;
use App\Enums\Vehicle\VehicleStatus;
use App\Models\Company;
use App\Models\Vehicle;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Vérifie que les triggers soft-delete autorisent la réutilisation d'une
 * valeur après soft-delete et rejettent les doublons actifs avec
 * `SQLSTATE 45000`.
 *
 * Couvre les 2 tables : companies, vehicles. (Le domaine Contract a
 * son propre test d'overlap dans SchemaSmokeTest.)
 */
final class SoftDeleteUniqueTriggersTest extends TestCase
{
    use RefreshDatabase;

    // ─── companies ───────────────────────────────────────────────────

    #[Test]
    public function un_short_code_actif_ne_peut_pas_etre_duplique(): void
    {
        $this->makeCompany(short: 'ACM');

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/short_code/');

        $this->makeCompany(short: 'ACM');
    }

    #[Test]
    public function un_short_code_libere_par_soft_delete_est_reutilisable(): void
    {
        $first = $this->makeCompany(short: 'ACM');
        $first->delete();

        $second = $this->makeCompany(short: 'ACM');

        $this->assertNotSame($first->id, $second->id);
        $this->assertSoftDeleted($first);
        $this->assertNull($second->deleted_at);
    }

    #[Test]
    public function un_siren_null_ne_provoque_jamais_de_collision(): void
    {
        $this->makeCompany(short: 'AAA', siren: null);
        $this->makeCompany(short: 'BBB', siren: null);

        $this->assertSame(2, Company::count());
    }

    #[Test]
    public function un_siren_actif_ne_peut_pas_etre_duplique(): void
    {
        $this->makeCompany(short: 'AAA', siren: '123456789');

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/siren/');

        $this->makeCompany(short: 'BBB', siren: '123456789');
    }

    // ─── vehicles ────────────────────────────────────────────────────

    #[Test]
    public function une_plaque_active_ne_peut_pas_etre_dupliquee(): void
    {
        $this->makeVehicle(plate: 'AA-001-AA');

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/license_plate/');

        $this->makeVehicle(plate: 'AA-001-AA');
    }

    #[Test]
    public function une_plaque_liberee_par_soft_delete_est_reutilisable(): void
    {
        $first = $this->makeVehicle(plate: 'AA-001-AA');
        $first->delete();

        $second = $this->makeVehicle(plate: 'AA-001-AA');

        $this->assertNotSame($first->id, $second->id);
    }

    #[Test]
    public function un_vin_null_ne_provoque_jamais_de_collision(): void
    {
        $this->makeVehicle(plate: 'AA-001-AA', vin: null);
        $this->makeVehicle(plate: 'BB-002-BB', vin: null);

        $this->assertSame(2, Vehicle::count());
    }

    // ─── helpers ─────────────────────────────────────────────────────

    private function makeCompany(string $short, ?string $siren = null): Company
    {
        return Company::create([
            'legal_name' => 'Test '.$short,
            'short_code' => $short,
            'color' => CompanyColor::Indigo,
            'siren' => $siren,
            'country' => 'FR',
            'is_active' => true,
        ]);
    }

    private function makeVehicle(string $plate, ?string $vin = '__nullable__'): Vehicle
    {
        return Vehicle::create([
            'license_plate' => $plate,
            'brand' => 'Test',
            'model' => 'V',
            'vin' => $vin === '__nullable__' ? 'VIN-'.$plate : $vin,
            'first_french_registration_date' => '2022-01-01',
            'first_origin_registration_date' => '2022-01-01',
            'first_economic_use_date' => '2022-01-02',
            'acquisition_date' => '2022-01-02',
            'current_status' => VehicleStatus::Active,
        ]);
    }
}
