<?php

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
 *   - UNIQUE (license_plate) filtré par soft delete — permet la re-saisie
 *     après soft delete (colonne générée `license_plate_active`).
 *   - UNIQUE (vin) filtré par soft delete si VIN renseigné
 *     (colonne générée `vin_active`).
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

            // UNIQUE filtrés par soft delete — via colonnes générées virtuelles.
            $table->string('license_plate_active', 20)
                ->virtualAs('CASE WHEN deleted_at IS NULL THEN license_plate END')
                ->nullable();
            $table->string('vin_active', 20)
                ->virtualAs('CASE WHEN deleted_at IS NULL AND vin IS NOT NULL THEN vin END')
                ->nullable();

            $table->unique('license_plate_active');
            $table->unique('vin_active');
        });

        // CHECK constraints — filet SQL défensif, MySQL uniquement
        // (SQLite ne supporte pas `ALTER TABLE ... ADD CONSTRAINT`).
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
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
