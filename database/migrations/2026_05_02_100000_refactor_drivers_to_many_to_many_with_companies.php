<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Refonte Driver ↔ Company en N:N (Phase 06 V1.2).
 *
 * Cf. plan d'exécution Phase 06 :
 * - Un conducteur peut désormais appartenir à plusieurs entreprises au cours
 *   du temps via une table pivot `driver_company` (joined_at, left_at).
 * - La désactivation d'un conducteur dans une entreprise se fait en posant
 *   `left_at` sur la pivot (pas de flag `is_active` global).
 * - Le soft delete `deleted_at` reste sur `drivers` pour la suppression complète.
 *
 * Stratégie de migration des données existantes :
 * - Pour chaque driver actuel, on crée 1 pivot avec
 *   joined_at = drivers.created_at, left_at = drivers.deactivated_at
 *   (NULL si is_active = true, deactivated_at sinon).
 * - Puis drop de drivers.company_id, is_active, deactivated_at.
 *
 * Down : recrée les colonnes + repeuple drivers.company_id depuis la 1re pivot
 * trouvée par driver, puis drop la pivot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_company', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('driver_id')
                ->constrained('drivers')
                ->cascadeOnDelete();
            $table->foreignId('company_id')
                ->constrained('companies')
                ->restrictOnDelete();
            $table->date('joined_at');
            $table->date('left_at')->nullable();
            $table->timestamps();

            $table->unique(['driver_id', 'company_id', 'joined_at'], 'driver_company_unique');
            $table->index(['company_id', 'left_at']);
            $table->index(['driver_id', 'left_at']);
        });

        // Migration des données existantes : 1 pivot par driver actuel.
        $existingDrivers = DB::table('drivers')
            ->select(['id', 'company_id', 'is_active', 'deactivated_at', 'created_at'])
            ->whereNotNull('company_id')
            ->get();

        $now = now();

        foreach ($existingDrivers as $driver) {
            $joinedAt = $driver->created_at !== null
                ? Carbon::parse($driver->created_at)->toDateString()
                : $now->toDateString();

            $leftAt = null;
            if (! (bool) $driver->is_active && $driver->deactivated_at !== null) {
                $leftAt = Carbon::parse($driver->deactivated_at)->toDateString();
            }

            DB::table('driver_company')->insert([
                'driver_id' => $driver->id,
                'company_id' => $driver->company_id,
                'joined_at' => $joinedAt,
                'left_at' => $leftAt,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::table('drivers', function (Blueprint $table): void {
            $table->dropForeign(['company_id']);
            $table->dropIndex(['company_id', 'is_active']);
            $table->dropColumn(['company_id', 'is_active', 'deactivated_at']);
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table): void {
            $table->foreignId('company_id')
                ->nullable()
                ->after('id')
                ->constrained('companies')
                ->restrictOnDelete();
            $table->boolean('is_active')->default(true)->after('last_name');
            $table->timestamp('deactivated_at')->nullable()->after('is_active');
            $table->index(['company_id', 'is_active']);
        });

        // Repopulate drivers.company_id + is_active + deactivated_at depuis
        // la première pivot trouvée par driver (best-effort restore).
        $pivots = DB::table('driver_company')
            ->select(['driver_id', 'company_id', 'left_at'])
            ->orderBy('joined_at')
            ->get()
            ->groupBy('driver_id');

        foreach ($pivots as $driverId => $rows) {
            $first = $rows->first();
            DB::table('drivers')
                ->where('id', $driverId)
                ->update([
                    'company_id' => $first->company_id,
                    'is_active' => $first->left_at === null,
                    'deactivated_at' => $first->left_at,
                ]);
        }

        Schema::table('drivers', function (Blueprint $table): void {
            $table->foreignId('company_id')
                ->nullable(false)
                ->change();
        });

        Schema::dropIfExists('driver_company');
    }
};
