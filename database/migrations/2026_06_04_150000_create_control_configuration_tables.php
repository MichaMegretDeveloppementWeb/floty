<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier B / B1 · global configuration foundation for regulatory controls.
 *
 * Additive and non-destructive (three new tables, no change to existing ones):
 *   - `control_definitions` : global catalog of control definitions (name,
 *     anchor, initial validity, recurring cycle, behaviour flags, optional
 *     per-definition reminder cycle override). Soft-deleted; FK RESTRICT.
 *   - `control_reminder_settings` : application singleton (id=1) holding the
 *     default reminder cycle plus the "always notify" recipient scalar.
 *   - `control_recipient_deltas` : polymorphic include/exclude recipient deltas
 *     resolved on the fly into the effective recipient list (no materialised
 *     list). B1 uses the `settings` (level 0 defaults) and `definition`
 *     (level 1 per-control) levels; the `vehicle` level + its FK land in B2.
 *
 * CHECK constraints (MySQL only, mirrors the rest of the schema) enforce the
 * enum domains, positive durations, per-level scope coherence, and that an
 * `include` delta always carries a recipient name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('control_definitions', function (Blueprint $table): void {
            $table->id();

            $table->string('name', 120);
            $table->string('anchor', 40)->default('first_origin_registration');

            $table->unsignedSmallInteger('initial_duration_value');
            $table->string('initial_duration_unit', 10);
            $table->unsignedSmallInteger('cycle_value');
            $table->string('cycle_unit', 10);

            $table->boolean('notify_driver')->default(false);
            $table->boolean('implies_unavailability')->default(false);

            // Per-definition reminder cycle override; NULL on a field = inherit
            // the matching value from `control_reminder_settings`.
            $table->unsignedSmallInteger('reminder_days_before')->nullable();
            $table->boolean('reminder_on_due_day')->nullable();
            $table->unsignedSmallInteger('reminder_repeat_every_days')->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('display_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
            $table->index('deleted_at');
        });

        Schema::create('control_reminder_settings', function (Blueprint $table): void {
            $table->id();

            $table->unsignedSmallInteger('days_before')->default(15);
            $table->boolean('remind_on_due_day')->default(true);
            $table->unsignedSmallInteger('repeat_every_days')->default(5);

            $table->string('always_notify_name', 120)->nullable();
            $table->string('always_notify_email', 180)->nullable();

            $table->timestamps();
        });

        Schema::create('control_recipient_deltas', function (Blueprint $table): void {
            $table->id();

            $table->string('level', 12);

            $table->foreignId('control_definition_id')
                ->nullable()
                ->constrained('control_definitions')
                ->restrictOnDelete();

            // FK to `vehicle_control_overrides` added in B2 (table does not exist yet).
            $table->unsignedBigInteger('vehicle_control_override_id')->nullable();

            $table->string('operation', 8);
            $table->string('email', 180);
            $table->string('name', 120)->nullable();

            $table->timestamps();

            $table->index(['level', 'control_definition_id']);
            $table->index('email');
        });

        // CHECK constraints: MySQL only (portable defaults already materialised above).
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE `control_definitions`
                ADD CONSTRAINT `chk_control_definitions_anchor`
                CHECK (anchor IN ('first_origin_registration', 'first_french_registration', 'acquisition', 'economic_use'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE `control_definitions`
                ADD CONSTRAINT `chk_control_definitions_units`
                CHECK (initial_duration_unit IN ('years', 'months') AND cycle_unit IN ('years', 'months'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE `control_definitions`
                ADD CONSTRAINT `chk_control_definitions_positive_durations`
                CHECK (initial_duration_value > 0 AND cycle_value > 0)
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE `control_definitions`
                ADD CONSTRAINT `chk_control_definitions_repeat_positive`
                CHECK (reminder_repeat_every_days IS NULL OR reminder_repeat_every_days > 0)
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE `control_reminder_settings`
                ADD CONSTRAINT `chk_control_reminder_settings_repeat_positive`
                CHECK (repeat_every_days > 0)
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE `control_recipient_deltas`
                ADD CONSTRAINT `chk_control_recipient_deltas_level`
                CHECK (level IN ('settings', 'definition', 'vehicle'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE `control_recipient_deltas`
                ADD CONSTRAINT `chk_control_recipient_deltas_operation`
                CHECK (operation IN ('include', 'exclude'))
        SQL);

        // Each level binds to exactly its own scope column (or none for settings).
        DB::statement(<<<'SQL'
            ALTER TABLE `control_recipient_deltas`
                ADD CONSTRAINT `chk_control_recipient_deltas_scope`
                CHECK (
                    (level = 'settings' AND control_definition_id IS NULL AND vehicle_control_override_id IS NULL)
                    OR (level = 'definition' AND control_definition_id IS NOT NULL AND vehicle_control_override_id IS NULL)
                    OR (level = 'vehicle' AND vehicle_control_override_id IS NOT NULL AND control_definition_id IS NULL)
                )
        SQL);

        // An include delta materialises a recipient, so it must carry a name.
        DB::statement(<<<'SQL'
            ALTER TABLE `control_recipient_deltas`
                ADD CONSTRAINT `chk_control_recipient_deltas_include_name`
                CHECK (operation = 'exclude' OR name IS NOT NULL)
        SQL);
    }

    public function down(): void
    {
        // Dropping the tables removes their CHECK constraints with them; order
        // respects the FK (deltas reference definitions).
        Schema::dropIfExists('control_recipient_deltas');
        Schema::dropIfExists('control_reminder_settings');
        Schema::dropIfExists('control_definitions');
    }
};
