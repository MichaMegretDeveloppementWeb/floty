<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Étend le CHECK constraint `chk_vehicles_exit_reason_enum` pour
 * accepter la valeur `'stolen_unrecovered'` (vol non résolu acté
 * définitivement).
 *
 * Cf. ADR-0018 rev. 1.1 § 3 (cycle de vie véhicule, ajout du 5ᵉ case
 * de l'enum `VehicleExitReason`). Voir aussi la distinction avec
 * `UnavailabilityType::theft` (ADR-0016) qui caractérise un vol
 * récent susceptible de résolution.
 *
 * Driver SQLite (tests legacy) : CHECK constraint non créé à
 * l'origine, rien à modifier.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE vehicles DROP CONSTRAINT chk_vehicles_exit_reason_enum');

        DB::statement(<<<'SQL'
            ALTER TABLE vehicles
                ADD CONSTRAINT chk_vehicles_exit_reason_enum
                CHECK (exit_reason IS NULL OR exit_reason IN (
                    'sold', 'destroyed', 'transferred', 'stolen_unrecovered', 'other'
                ))
        SQL);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE vehicles DROP CONSTRAINT chk_vehicles_exit_reason_enum');

        DB::statement(<<<'SQL'
            ALTER TABLE vehicles
                ADD CONSTRAINT chk_vehicles_exit_reason_enum
                CHECK (exit_reason IS NULL OR exit_reason IN (
                    'sold', 'destroyed', 'transferred', 'other'
                ))
        SQL);
    }
};
