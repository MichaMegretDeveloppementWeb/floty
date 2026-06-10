<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Refonte type -> nature : l'identite d'un evenement devient son nom libre
 * (`title`, desormais NOT NULL) + ses natures (table enfant
 * `vehicle_event_categories`, libellees « Nature » dans l'UI). La colonne
 * `type` et ses 3 CHECK disparaissent ; `has_fiscal_impact` est desormais
 * derive au write des natures reductrices du catalogue
 * (`vehicle_event_natures.is_fiscally_reductive`), le moteur fiscal continue
 * de lire uniquement ce booleen fige.
 *
 * Nouveau CHECK : un evenement fiscalement reducteur implique toujours une
 * indisponibilite (la checkbox est forcee cote serveur).
 *
 * Schema pur : la table prod est vide (aucun evenement saisi), le dev se
 * reconstruit par migrate:fresh --seed. Le passage de `title` en NOT NULL
 * echouerait sur des lignes a titre NULL : garde-fou voulu, pas de backfill
 * silencieux.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `vehicle_events` DROP CONSTRAINT `chk_vehicle_events_type_enum`');
            DB::statement('ALTER TABLE `vehicle_events` DROP CONSTRAINT `chk_vehicle_events_fiscal_impact_consistent`');
            DB::statement('ALTER TABLE `vehicle_events` DROP CONSTRAINT `chk_vehicle_events_custom_fields`');
            DB::statement('ALTER TABLE `vehicle_events` DROP CONSTRAINT `chk_vehicle_events_implies_consistency`');
        }

        Schema::table('vehicle_events', function (Blueprint $table): void {
            $table->dropColumn('type');
        });

        Schema::table('vehicle_events', function (Blueprint $table): void {
            $table->string('title')->nullable(false)->change();
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
                ALTER TABLE `vehicle_events`
                    ADD CONSTRAINT `chk_vehicle_events_fiscal_implies_unavailability`
                    CHECK (has_fiscal_impact = 0 OR implies_unavailability = 1)
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `vehicle_events` DROP CONSTRAINT `chk_vehicle_events_fiscal_implies_unavailability`');
        }

        Schema::table('vehicle_events', function (Blueprint $table): void {
            $table->string('title')->nullable()->change();
        });

        // `type` revient nullable : ses valeurs ne sont pas restaurables. Les
        // anciens CHECK ne sont pas recrees (un type NULL les satisferait par
        // vacuite, ils ne protegeraient plus rien).
        Schema::table('vehicle_events', function (Blueprint $table): void {
            $table->string('type', 50)->nullable()->after('vehicle_id');
        });
    }
};
