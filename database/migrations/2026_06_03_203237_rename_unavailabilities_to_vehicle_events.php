<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier A / A1 · renames the `unavailabilities` domain to `vehicle_events`.
 *
 * Iso-functional rename: data, columns, the `type` ENUM values and the CHECK
 * logic are preserved. Only NAMES change (tables, the documents FK column
 * `unavailability_id` -> `vehicle_event_id`, the three CHECK constraints, the
 * named FK constraints and the explicit deleted_at index). The original
 * `create_unavailabilities_table` migration is left untouched (already applied
 * in production); this runs after it, identically on tests (MySQL) and prod.
 *
 * Auto-generated composite index names keep their original prefix
 * (`unavailabilities_*`): purely cosmetic, never referenced by name, no
 * functional impact. Drop by explicit name if ever needed in a later migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('unavailabilities', 'vehicle_events');
        Schema::rename('unavailability_documents', 'vehicle_event_documents');

        if (DB::connection()->getDriverName() !== 'mysql') {
            Schema::table('vehicle_event_documents', function (Blueprint $table): void {
                $table->renameColumn('unavailability_id', 'vehicle_event_id');
            });

            return;
        }

        // Named FK constraints carried over keep the old table prefix · rename them.
        DB::statement('ALTER TABLE `vehicle_events` DROP FOREIGN KEY `unavailabilities_vehicle_id_foreign`');
        DB::statement('ALTER TABLE `vehicle_events` ADD CONSTRAINT `vehicle_events_vehicle_id_foreign` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`)');

        // Documents FK column rename (drop FK, rename column, re-add FK with the new name).
        DB::statement('ALTER TABLE `vehicle_event_documents` DROP FOREIGN KEY `unavailability_documents_unavailability_id_foreign`');
        DB::statement('ALTER TABLE `vehicle_event_documents` RENAME COLUMN `unavailability_id` TO `vehicle_event_id`');
        DB::statement('ALTER TABLE `vehicle_event_documents` ADD CONSTRAINT `vehicle_event_documents_vehicle_event_id_foreign` FOREIGN KEY (`vehicle_event_id`) REFERENCES `vehicle_events` (`id`)');

        DB::statement('ALTER TABLE `vehicle_event_documents` DROP FOREIGN KEY `unavailability_documents_uploaded_by_foreign`');
        DB::statement('ALTER TABLE `vehicle_event_documents` ADD CONSTRAINT `vehicle_event_documents_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`)');

        // CHECK constraints: identical logic, names re-prefixed.
        DB::statement('ALTER TABLE `vehicle_events` DROP CONSTRAINT `chk_unavailabilities_dates_ordered`');
        DB::statement('ALTER TABLE `vehicle_events` DROP CONSTRAINT `chk_unavailabilities_type_enum`');
        DB::statement('ALTER TABLE `vehicle_events` DROP CONSTRAINT `chk_unavailabilities_fiscal_impact_consistent`');

        DB::statement(<<<'SQL'
            ALTER TABLE `vehicle_events`
                ADD CONSTRAINT `chk_vehicle_events_dates_ordered`
                CHECK (end_date IS NULL OR start_date <= end_date)
        SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `vehicle_events`
                ADD CONSTRAINT `chk_vehicle_events_type_enum`
                CHECK (type IN (
                    'maintenance',
                    'technical_inspection',
                    'accident_repair',
                    'accident_no_circulation',
                    'pound_public',
                    'pound_private',
                    'ci_suspension',
                    'theft',
                    'other'
                ))
        SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE `vehicle_events`
                ADD CONSTRAINT `chk_vehicle_events_fiscal_impact_consistent`
                CHECK (has_fiscal_impact = (type IN (
                    'accident_no_circulation',
                    'pound_public',
                    'ci_suspension'
                )))
        SQL);

        DB::statement('ALTER TABLE `vehicle_events` RENAME INDEX `unavailabilities_deleted_at_idx` TO `vehicle_events_deleted_at_idx`');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `vehicle_events` RENAME INDEX `vehicle_events_deleted_at_idx` TO `unavailabilities_deleted_at_idx`');

            DB::statement('ALTER TABLE `vehicle_events` DROP CONSTRAINT `chk_vehicle_events_fiscal_impact_consistent`');
            DB::statement('ALTER TABLE `vehicle_events` DROP CONSTRAINT `chk_vehicle_events_type_enum`');
            DB::statement('ALTER TABLE `vehicle_events` DROP CONSTRAINT `chk_vehicle_events_dates_ordered`');

            DB::statement(<<<'SQL'
                ALTER TABLE `vehicle_events`
                    ADD CONSTRAINT `chk_unavailabilities_dates_ordered`
                    CHECK (end_date IS NULL OR start_date <= end_date)
            SQL);
            DB::statement(<<<'SQL'
                ALTER TABLE `vehicle_events`
                    ADD CONSTRAINT `chk_unavailabilities_type_enum`
                    CHECK (type IN (
                        'maintenance',
                        'technical_inspection',
                        'accident_repair',
                        'accident_no_circulation',
                        'pound_public',
                        'pound_private',
                        'ci_suspension',
                        'theft',
                        'other'
                    ))
            SQL);
            DB::statement(<<<'SQL'
                ALTER TABLE `vehicle_events`
                    ADD CONSTRAINT `chk_unavailabilities_fiscal_impact_consistent`
                    CHECK (has_fiscal_impact = (type IN (
                        'accident_no_circulation',
                        'pound_public',
                        'ci_suspension'
                    )))
            SQL);

            DB::statement('ALTER TABLE `vehicle_events` DROP FOREIGN KEY `vehicle_events_vehicle_id_foreign`');
            DB::statement('ALTER TABLE `vehicle_event_documents` DROP FOREIGN KEY `vehicle_event_documents_uploaded_by_foreign`');
            DB::statement('ALTER TABLE `vehicle_event_documents` DROP FOREIGN KEY `vehicle_event_documents_vehicle_event_id_foreign`');
            DB::statement('ALTER TABLE `vehicle_event_documents` RENAME COLUMN `vehicle_event_id` TO `unavailability_id`');

            Schema::rename('vehicle_event_documents', 'unavailability_documents');
            Schema::rename('vehicle_events', 'unavailabilities');

            DB::statement('ALTER TABLE `unavailabilities` ADD CONSTRAINT `unavailabilities_vehicle_id_foreign` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`)');
            DB::statement('ALTER TABLE `unavailability_documents` ADD CONSTRAINT `unavailability_documents_unavailability_id_foreign` FOREIGN KEY (`unavailability_id`) REFERENCES `unavailabilities` (`id`)');
            DB::statement('ALTER TABLE `unavailability_documents` ADD CONSTRAINT `unavailability_documents_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`)');

            return;
        }

        Schema::table('vehicle_event_documents', function (Blueprint $table): void {
            $table->renameColumn('vehicle_event_id', 'unavailability_id');
        });

        Schema::rename('vehicle_event_documents', 'unavailability_documents');
        Schema::rename('vehicle_events', 'unavailabilities');
    }
};
