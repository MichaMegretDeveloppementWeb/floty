<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot `contract_drivers` — passage du modèle 1:N (un seul driver_id sur
 * `contracts`) à un modèle N:N (un contrat peut avoir 0, 1 ou plusieurs
 * conducteurs).
 *
 * **Stratégie de migration (clean break)** :
 *   1. Création de la table pivot `contract_drivers` (FK cascade des deux
 *      côtés, unique pour interdire les doublons d'un même driver sur un
 *      même contrat).
 *   2. Migration des données existantes : pour chaque contrat avec un
 *      `driver_id` non null, insertion d'une ligne pivot équivalente.
 *   3. Suppression de la colonne `contracts.driver_id` (FK puis colonne).
 *
 * Au down, on inverse : recréation de `driver_id` nullable, copie du
 * **premier** driver de chaque contrat depuis le pivot, drop du pivot.
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

        // Migration data : copier les drivers existants vers le pivot.
        // `whereNotNull` rend l'opération idempotente sur seed/dev.
        DB::table('contracts')
            ->whereNotNull('driver_id')
            ->select(['id', 'driver_id'])
            ->orderBy('id')
            ->each(function (object $row): void {
                DB::table('contract_drivers')->insert([
                    'contract_id' => $row->id,
                    'driver_id' => $row->driver_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        // Drop FK + colonne `contracts.driver_id`.
        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropForeign(['driver_id']);
            $table->dropColumn('driver_id');
        });
    }

    public function down(): void
    {
        // Recréer la colonne nullable.
        Schema::table('contracts', function (Blueprint $table): void {
            $table->foreignId('driver_id')
                ->nullable()
                ->after('company_id')
                ->constrained('drivers')
                ->nullOnDelete();
        });

        // Migration data inverse : prendre le 1er driver de chaque contrat
        // (par id pivot croissant). Down est best-effort uniquement.
        $rows = DB::table('contract_drivers')
            ->select(['contract_id', 'driver_id'])
            ->orderBy('id')
            ->get();

        $firstByContract = [];
        foreach ($rows as $row) {
            if (! isset($firstByContract[$row->contract_id])) {
                $firstByContract[$row->contract_id] = $row->driver_id;
            }
        }

        foreach ($firstByContract as $contractId => $driverId) {
            DB::table('contracts')
                ->where('id', $contractId)
                ->update(['driver_id' => $driverId]);
        }

        Schema::dropIfExists('contract_drivers');
    }
};
