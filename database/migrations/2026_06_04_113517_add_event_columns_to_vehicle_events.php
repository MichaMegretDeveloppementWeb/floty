<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier A / A2 · enriches `vehicle_events` so an event can carry a custom
 * identity and an explicit unavailability flag, separate from its fiscal one.
 *
 * Additive and non-destructive:
 *   - `title` / `category` : free text, populated ONLY for the `other` type
 *     (known types derive their label and category from the enum on the
 *     front). Null for every existing row.
 *   - `implies_unavailability` : informative "the vehicle is unavailable
 *     these days" flag (heatmap / usage / timeline / exit). Defaults to true,
 *     which backfills every existing row (they all were unavailabilities).
 *
 * Two CHECK constraints enforce the orthogonal-axes invariants (added after
 * the columns are populated so existing rows already satisfy them):
 *   - known types always imply unavailability (only `other` may opt out);
 *   - `title`/`category` are reserved to the `other` type.
 *
 * The fiscal axis (`has_fiscal_impact` + its CHECK) is untouched: `other` is
 * never fiscally reductive, so the 53 fiscal goldens stay green by construction.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_events', function (Blueprint $table): void {
            $table->string('title')->nullable()->after('type');
            $table->string('category')->nullable()->after('title');
            $table->boolean('implies_unavailability')->default(true)->after('has_fiscal_impact');
        });

        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        // Known types always imply unavailability; only `other` may opt out.
        DB::statement(<<<'SQL'
            ALTER TABLE `vehicle_events`
                ADD CONSTRAINT `chk_vehicle_events_implies_consistency`
                CHECK (implies_unavailability = 1 OR type = 'other')
        SQL);

        // Custom title/category are reserved to the `other` type; known types
        // derive their label and category from the enum.
        DB::statement(<<<'SQL'
            ALTER TABLE `vehicle_events`
                ADD CONSTRAINT `chk_vehicle_events_custom_fields`
                CHECK (type = 'other' OR (title IS NULL AND category IS NULL))
        SQL);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `vehicle_events` DROP CHECK `chk_vehicle_events_custom_fields`');
            DB::statement('ALTER TABLE `vehicle_events` DROP CHECK `chk_vehicle_events_implies_consistency`');
        }

        Schema::table('vehicle_events', function (Blueprint $table): void {
            $table->dropColumn(['title', 'category', 'implies_unavailability']);
        });
    }
};
