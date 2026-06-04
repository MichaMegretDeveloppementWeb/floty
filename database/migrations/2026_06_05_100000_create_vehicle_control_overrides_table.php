<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier B / B2 · per-vehicle controls.
 *
 * A `vehicle_control_overrides` row serves two jobs, discriminated by
 * `control_definition_id`:
 *   - SET   : a sparse override of a GLOBAL control definition for this vehicle
 *     (non-null fields override, null fields inherit the global) + a status
 *     (active / paused / disabled).
 *   - NULL  : a vehicle-SPECIFIC control carrying its own complete échéance
 *     recipe (enforced by the row-kind CHECK).
 *
 * Sparse by design: a row exists only when the vehicle customises / pauses /
 * disables a global control, or adds a specific one. No mass generation.
 * `unique(vehicle_id, control_definition_id)` allows at most one override per
 * (vehicle, global definition); MySQL treats the NULL control_definition_id as
 * distinct, so a vehicle may still hold many vehicle-specific rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_control_overrides', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('vehicle_id')
                ->constrained('vehicles')
                ->restrictOnDelete();

            $table->foreignId('control_definition_id')
                ->nullable()
                ->constrained('control_definitions')
                ->restrictOnDelete();

            $table->string('status', 10)->default('active');

            // Sparse override fields; NULL = inherit the global (override row)
            // or required-by-CHECK (vehicle-specific row).
            $table->string('name', 120)->nullable();
            $table->string('anchor', 40)->nullable();
            $table->unsignedSmallInteger('initial_duration_value')->nullable();
            $table->string('initial_duration_unit', 10)->nullable();
            $table->unsignedSmallInteger('cycle_value')->nullable();
            $table->string('cycle_unit', 10)->nullable();
            $table->boolean('notify_driver')->nullable();
            $table->boolean('implies_unavailability')->nullable();
            $table->unsignedSmallInteger('reminder_days_before')->nullable();
            $table->boolean('reminder_on_due_day')->nullable();
            $table->unsignedSmallInteger('reminder_repeat_every_days')->nullable();

            $table->unsignedSmallInteger('display_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['vehicle_id', 'status']);
            $table->index('deleted_at');
            $table->unique(['vehicle_id', 'control_definition_id'], 'vco_vehicle_definition_unique');
        });

        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE `vehicle_control_overrides`
                ADD CONSTRAINT `chk_vco_status`
                CHECK (status IN ('active', 'paused', 'disabled'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE `vehicle_control_overrides`
                ADD CONSTRAINT `chk_vco_anchor`
                CHECK (anchor IS NULL OR anchor IN ('first_origin_registration', 'first_french_registration', 'acquisition', 'economic_use'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE `vehicle_control_overrides`
                ADD CONSTRAINT `chk_vco_units`
                CHECK (
                    (initial_duration_unit IS NULL OR initial_duration_unit IN ('years', 'months'))
                    AND (cycle_unit IS NULL OR cycle_unit IN ('years', 'months'))
                )
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE `vehicle_control_overrides`
                ADD CONSTRAINT `chk_vco_positive`
                CHECK (
                    (initial_duration_value IS NULL OR initial_duration_value > 0)
                    AND (cycle_value IS NULL OR cycle_value > 0)
                    AND (reminder_repeat_every_days IS NULL OR reminder_repeat_every_days > 0)
                )
        SQL);

        // A vehicle-specific control (no control_definition_id) must carry a
        // complete échéance recipe; an override may leave any field NULL.
        DB::statement(<<<'SQL'
            ALTER TABLE `vehicle_control_overrides`
                ADD CONSTRAINT `chk_vco_specific_complete`
                CHECK (
                    control_definition_id IS NOT NULL
                    OR (
                        name IS NOT NULL
                        AND anchor IS NOT NULL
                        AND initial_duration_value IS NOT NULL AND initial_duration_unit IS NOT NULL
                        AND cycle_value IS NOT NULL AND cycle_unit IS NOT NULL
                    )
                )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_control_overrides');
    }
};
