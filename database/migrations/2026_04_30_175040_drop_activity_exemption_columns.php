<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retire la mécanique « exonération à activité » (R-2024-022) du
 * schéma : la colonne `affected_to_exempted_activity_percent` sur
 * `vehicle_fiscal_characteristics` était architecturalement mal
 * placée (une caractéristique d'usage par entreprise n'a rien à
 * faire sur la VFC), et la colonne `exempted_activity` sur
 * `companies` n'était jamais saisissable. Décision produit V1 : on
 * retire la mécanique entièrement, V2 (si besoin) la réintroduira
 * proprement côté `contracts` avec un design réfléchi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_fiscal_characteristics', function (Blueprint $table): void {
            $table->dropColumn('affected_to_exempted_activity_percent');
        });

        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn('exempted_activity');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_fiscal_characteristics', function (Blueprint $table): void {
            $table->unsignedTinyInteger('affected_to_exempted_activity_percent')->default(0);
        });

        Schema::table('companies', function (Blueprint $table): void {
            $table->string('exempted_activity', 32)->default('none');
        });
    }
};
