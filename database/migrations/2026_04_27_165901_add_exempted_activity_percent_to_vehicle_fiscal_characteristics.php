<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1.9 — Ajout du pourcentage d'affectation à une activité
 * exonérée sur la version effective des caractéristiques fiscales
 * d'un véhicule (R-2024-022).
 *
 * Valeur par défaut 0 : aucune affectation à une activité exonérée.
 * En V1, la règle R-022 considère **uniquement** la valeur 100
 * (affectation totale) comme déclencheur de l'exonération — un prorata
 * partiel sera traité dans une V2 si demandé par le client.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_fiscal_characteristics', function (Blueprint $table): void {
            $table->unsignedTinyInteger('affected_to_exempted_activity_percent')
                ->default(0)
                ->after('n1_ski_lift_use');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_fiscal_characteristics', function (Blueprint $table): void {
            $table->dropColumn('affected_to_exempted_activity_percent');
        });
    }
};
