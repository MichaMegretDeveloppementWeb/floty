<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bascule la FK `contracts.vehicle_id` de `cascadeOnDelete` vers
 * `restrictOnDelete` (audit M6 du 2026-05-04).
 *
 * **Pourquoi** : un contrat est une trace fiscale opposable — chaque
 * ligne sert au calcul de la TVS d'une entreprise sur une année. Une
 * cascade silencieuse au hard delete d'un véhicule détruirait sans alerte
 * l'historique fiscal de toutes les entreprises qui l'ont loué. Le
 * cycle de vie « véhicule sorti » passe par `vehicles.exit_date` /
 * `exit_reason` (ADR-0018), pas par un `forceDelete`. Il n'existe
 * d'ailleurs aucune route de destruction côté UI : la voie normale est
 * le soft delete (qui n'active pas la FK) ou la sortie de flotte.
 *
 * `restrictOnDelete` formalise cette doctrine au niveau DB : si un jour
 * un script ou un test tente un `Vehicle::forceDelete()` alors que des
 * contrats existent encore (même soft-deletés), MySQL refuse plutôt que
 * d'effacer en silence. Le développeur doit alors soit purger
 * explicitement les contrats, soit choisir le soft delete — choix
 * conscient, pas effet de bord.
 *
 * Les autres FK restent inchangées :
 *   - `company_id` était déjà en `restrictOnDelete` (cohérent),
 *   - `driver_id` reste en `nullOnDelete` (un conducteur peut quitter
 *     l'organisation sans invalider l'historique).
 *
 * **Driver SQLite** (tests legacy) : le drop+recreate est exécuté sur
 * MySQL uniquement. SQLite ne supporte pas `ALTER TABLE DROP FOREIGN KEY`
 * proprement et la suite de tests `RefreshDatabase` recrée la base à
 * chaque run, donc l'invariant n'a pas besoin d'être migré là-bas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropForeign(['vehicle_id']);
            $table->foreign('vehicle_id')
                ->references('id')
                ->on('vehicles')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropForeign(['vehicle_id']);
            $table->foreign('vehicle_id')
                ->references('id')
                ->on('vehicles')
                ->cascadeOnDelete();
        });
    }
};
