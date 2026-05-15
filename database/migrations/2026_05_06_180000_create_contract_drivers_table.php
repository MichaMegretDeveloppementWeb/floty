<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot `contract_drivers` · passage du modèle 1:N (un seul driver_id sur
 * `contracts`) à un modèle N:N (un contrat peut avoir 0, 1 ou plusieurs
 * conducteurs).
 *
 * Doctrine `migrations.md` · schéma strict, zéro data. Le data load
 * historique (rétro-migration `contracts.driver_id` → pivot) a été retiré
 * Lot 6 D3 · `migrate:fresh + DemoSeeder` reconstitue l'état attendu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_drivers', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('contract_id')
                ->constrained('contracts')
                ->cascadeOnDelete();
            $table->foreignId('driver_id')
                ->constrained('drivers')
                ->cascadeOnDelete();

            $table->timestamps();

            // Un même conducteur ne peut figurer qu'une fois sur un contrat
            // (équivalent applicatif de la règle DTO `distinct`).
            $table->unique(['contract_id', 'driver_id']);

            // Index inverse pour les requêtes « contrats où ce driver figure ».
            $table->index('driver_id');
        });

        // Drop FK + colonne `contracts.driver_id`.
        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropForeign(['driver_id']);
            $table->dropColumn('driver_id');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->foreignId('driver_id')
                ->nullable()
                ->after('company_id')
                ->constrained('drivers')
                ->nullOnDelete();
        });

        Schema::dropIfExists('contract_drivers');
    }
};
