<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Table `vehicles` — Registre des véhicules.
 *
 * Cf. 01-schema-metier.md § 2.
 *
 * Particularités :
 *   - UNIQUE (license_plate) filtré par soft delete via triggers
 *     `vehicles_license_plate_active_*` — permet la re-saisie après soft
 *     delete (MySQL refuse les expressions conditionnelles dans GENERATED
 *     ALWAYS AS, donc on émule via SIGNAL).
 *   - UNIQUE (vin) filtré idem si VIN renseigné.
 *   - Caractéristiques **fiscales** (co2, norme Euro, source énergie, etc.)
 *     dans `vehicle_fiscal_characteristics` — cette table-ci ne porte que
 *     les attributs **non fiscaux** (identité, cycle de vie).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table): void {
            $table->id();

            $table->string('license_plate', 20);
            $table->string('brand', 80);
            $table->string('model', 120);
            $table->string('vin', 20)->nullable();
            $table->string('color', 30)->nullable();
            $table->string('photo_path', 500)->nullable();

            $table->date('first_french_registration_date');
            $table->date('first_origin_registration_date');
            $table->date('first_economic_use_date');
            $table->date('acquisition_date');
            $table->date('exit_date')->nullable();
            $table->string('exit_reason', 30)->nullable();

            $table->string('current_status', 30)->default('active');

            $table->unsignedInteger('mileage_current')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('exit_date');
            $table->index('license_plate');
            $table->index('vin');
        });

        // CHECK constraints + triggers — MySQL uniquement.
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE vehicles
                ADD CONSTRAINT chk_vehicles_exit_reason_when_exited
                CHECK (exit_date IS NULL OR exit_reason IS NOT NULL)
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE vehicles
                ADD CONSTRAINT chk_vehicles_registration_dates_ordered
                CHECK (first_origin_registration_date <= first_french_registration_date)
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE vehicles
                ADD CONSTRAINT chk_vehicles_exit_reason_enum
                CHECK (exit_reason IS NULL OR exit_reason IN ('sold', 'destroyed', 'transferred', 'other'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE vehicles
                ADD CONSTRAINT chk_vehicles_status_enum
                CHECK (current_status IN ('active', 'maintenance', 'sold', 'destroyed', 'other'))
        SQL);

        $this->createSoftDeleteTriggers();
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            $this->dropSoftDeleteTriggers();
        }

        Schema::dropIfExists('vehicles');
    }

    private function createSoftDeleteTriggers(): void
    {
        // vehicles.license_plate (NOT NULL)
        $licensePlateBody = <<<'SQL'
            DECLARE clash_count INT;
            IF NEW.deleted_at IS NULL THEN
                SELECT COUNT(*) INTO clash_count
                FROM vehicles
                WHERE license_plate = NEW.license_plate
                  AND deleted_at IS NULL
                  AND id <> COALESCE(NEW.id, 0);
                IF clash_count > 0 THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'vehicles: license_plate already used by an active vehicle';
                END IF;
            END IF;
        SQL;
        DB::unprepared('CREATE TRIGGER vehicles_license_plate_active_insert BEFORE INSERT ON vehicles FOR EACH ROW BEGIN '.$licensePlateBody.' END');
        DB::unprepared('CREATE TRIGGER vehicles_license_plate_active_update BEFORE UPDATE ON vehicles FOR EACH ROW BEGIN '.$licensePlateBody.' END');

        // vehicles.vin (NULL toléré)
        $vinBody = <<<'SQL'
            DECLARE clash_count INT;
            IF NEW.deleted_at IS NULL AND NEW.vin IS NOT NULL THEN
                SELECT COUNT(*) INTO clash_count
                FROM vehicles
                WHERE vin = NEW.vin
                  AND deleted_at IS NULL
                  AND id <> COALESCE(NEW.id, 0);
                IF clash_count > 0 THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'vehicles: vin already used by an active vehicle';
                END IF;
            END IF;
        SQL;
        DB::unprepared('CREATE TRIGGER vehicles_vin_active_insert BEFORE INSERT ON vehicles FOR EACH ROW BEGIN '.$vinBody.' END');
        DB::unprepared('CREATE TRIGGER vehicles_vin_active_update BEFORE UPDATE ON vehicles FOR EACH ROW BEGIN '.$vinBody.' END');
    }

    private function dropSoftDeleteTriggers(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS vehicles_license_plate_active_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS vehicles_license_plate_active_update');
        DB::unprepared('DROP TRIGGER IF EXISTS vehicles_vin_active_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS vehicles_vin_active_update');
    }
};
