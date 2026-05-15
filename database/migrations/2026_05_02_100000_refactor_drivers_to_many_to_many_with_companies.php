<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refonte Driver ↔ Company en N:N (Phase 06 V1.2).
 *
 * Cf. plan d'exécution Phase 06 ·
 * - Un conducteur peut désormais appartenir à plusieurs entreprises au cours
 *   du temps via une table pivot `driver_company` (joined_at, left_at).
 * - La désactivation d'un conducteur dans une entreprise se fait en posant
 *   `left_at` sur la pivot (pas de flag `is_active` global).
 * - Le soft delete `deleted_at` reste sur `drivers` pour la suppression complète.
 *
 * Doctrine `migrations.md` · schéma strict, zéro data. Le data load
 * historique (rétro-migration depuis l'ancien schéma 1:N) a été retiré
 * Lot 6 D3 · `migrate:fresh + DemoSeeder` reconstitue l'état attendu.
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

        Schema::dropIfExists('driver_company');
    }
};
