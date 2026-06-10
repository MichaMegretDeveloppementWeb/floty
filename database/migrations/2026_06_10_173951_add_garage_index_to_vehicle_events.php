<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index sur `garage` : la liste d'autosuggestion des filtres lit
 * `SELECT DISTINCT garage` a chaque affichage (formulaire + index global),
 * l'index transforme ce scan en parcours d'index borne par le nombre de
 * garages distincts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_events', function (Blueprint $table): void {
            $table->index('garage');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_events', function (Blueprint $table): void {
            $table->dropIndex(['garage']);
        });
    }
};
