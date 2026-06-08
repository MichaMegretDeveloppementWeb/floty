<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier A · multi categories per vehicle event (up to 5), free text with
 * autocomplete + future SQL filtering ("tous les événements de catégorie X").
 *
 * Moves the single `vehicle_events.category` string to a normalized child
 * table `vehicle_event_categories` (one row per category, indexed). Backfill:
 *   - known types -> their type default category (was derived on the front);
 *   - `other` -> the existing free `category`.
 * Also retires the `technical_inspection` type (now covered by regulatory
 * controls): existing rows are converted to `other` (title "Contrôle
 * technique"), keeping their "Contrôle réglementaire" category. The type and
 * custom-fields CHECK constraints are retuned, then the old column dropped.
 *
 * Additive + backfill + drop of a not-yet-deployed column: safe on prod (the
 * events feature is not live there). Events are empty on a fresh test DB, so
 * this is a pure schema change there.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_event_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vehicle_event_id')
                ->constrained('vehicle_events')
                ->cascadeOnDelete();
            $table->string('category', 60);
            $table->timestamps();

            $table->unique(['vehicle_event_id', 'category'], 'vec_event_category_unique');
            $table->index('category');
        });

        $isMysql = DB::connection()->getDriverName() === 'mysql';

        if ($isMysql) {
            // Backfill known types from their default category (TI included,
            // so a converted TI event keeps its "Contrôle réglementaire").
            DB::statement(<<<'SQL'
                INSERT INTO `vehicle_event_categories` (`vehicle_event_id`, `category`, `created_at`, `updated_at`)
                SELECT `id`,
                    CASE `type`
                        WHEN 'accident_no_circulation' THEN 'Accident'
                        WHEN 'accident_repair' THEN 'Accident'
                        WHEN 'pound_public' THEN 'Fourrière'
                        WHEN 'pound_private' THEN 'Fourrière'
                        WHEN 'ci_suspension' THEN 'Administratif'
                        WHEN 'maintenance' THEN 'Entretien'
                        WHEN 'technical_inspection' THEN 'Contrôle réglementaire'
                        WHEN 'theft' THEN 'Vol'
                    END,
                    NOW(), NOW()
                FROM `vehicle_events`
                WHERE `type` <> 'other'
            SQL);

            // Backfill custom events from their free category (when set).
            DB::statement(<<<'SQL'
                INSERT INTO `vehicle_event_categories` (`vehicle_event_id`, `category`, `created_at`, `updated_at`)
                SELECT `id`, TRIM(`category`), NOW(), NOW()
                FROM `vehicle_events`
                WHERE `type` = 'other' AND `category` IS NOT NULL AND TRIM(`category`) <> ''
            SQL);

            // Retire technical_inspection -> custom event (its category is
            // already backfilled above). Must run before tightening the CHECK.
            DB::statement(<<<'SQL'
                UPDATE `vehicle_events`
                SET `type` = 'other', `title` = 'Contrôle technique'
                WHERE `type` = 'technical_inspection'
            SQL);

            // Retune the type enum CHECK (drop technical_inspection) and the
            // custom-fields CHECK (it referenced `category`, dropped below).
            DB::statement('ALTER TABLE `vehicle_events` DROP CONSTRAINT `chk_vehicle_events_custom_fields`');
            DB::statement('ALTER TABLE `vehicle_events` DROP CONSTRAINT `chk_vehicle_events_type_enum`');
            DB::statement(<<<'SQL'
                ALTER TABLE `vehicle_events`
                    ADD CONSTRAINT `chk_vehicle_events_type_enum`
                    CHECK (type IN (
                        'accident_no_circulation',
                        'pound_public',
                        'ci_suspension',
                        'maintenance',
                        'accident_repair',
                        'pound_private',
                        'theft',
                        'other'
                    ))
            SQL);
        }

        Schema::table('vehicle_events', function (Blueprint $table): void {
            $table->dropColumn('category');
        });

        if ($isMysql) {
            // Custom title is reserved to the `other` type (categories now live
            // in the child table, so the constraint only guards `title`).
            DB::statement(<<<'SQL'
                ALTER TABLE `vehicle_events`
                    ADD CONSTRAINT `chk_vehicle_events_custom_fields`
                    CHECK (type = 'other' OR title IS NULL)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::table('vehicle_events', function (Blueprint $table): void {
            $table->string('category')->nullable()->after('title');
        });

        $isMysql = DB::connection()->getDriverName() === 'mysql';

        if ($isMysql) {
            // Best-effort restore of the single category from the child rows
            // (first category per event); the type conversion is not reversed.
            DB::statement(<<<'SQL'
                UPDATE `vehicle_events` ve
                SET `category` = (
                    SELECT vec.`category`
                    FROM `vehicle_event_categories` vec
                    WHERE vec.`vehicle_event_id` = ve.`id`
                    ORDER BY vec.`id`
                    LIMIT 1
                )
                WHERE ve.`type` = 'other'
            SQL);

            DB::statement('ALTER TABLE `vehicle_events` DROP CONSTRAINT `chk_vehicle_events_custom_fields`');
            DB::statement('ALTER TABLE `vehicle_events` DROP CONSTRAINT `chk_vehicle_events_type_enum`');
            DB::statement(<<<'SQL'
                ALTER TABLE `vehicle_events`
                    ADD CONSTRAINT `chk_vehicle_events_type_enum`
                    CHECK (type IN (
                        'accident_no_circulation',
                        'pound_public',
                        'ci_suspension',
                        'maintenance',
                        'technical_inspection',
                        'accident_repair',
                        'pound_private',
                        'theft',
                        'other'
                    ))
            SQL);
            DB::statement(<<<'SQL'
                ALTER TABLE `vehicle_events`
                    ADD CONSTRAINT `chk_vehicle_events_custom_fields`
                    CHECK (type = 'other' OR (title IS NULL AND category IS NULL))
            SQL);
        }

        Schema::dropIfExists('vehicle_event_categories');
    }
};
