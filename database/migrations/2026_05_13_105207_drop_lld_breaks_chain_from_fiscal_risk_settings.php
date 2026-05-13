<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 13 D5.10.Q · suppression de `lld_breaks_chain` du singleton
 * `fiscal_risk_settings`.
 *
 * Sous la doctrine D5.10.N validée, la chaîne LCD vise la continuité
 * temporelle d'usage indépendamment du véhicule (argument anti-fraude).
 * Un LLD intercalé sur un autre véhicule n'a aucun rapport métier avec
 * une chaîne LCD donnée et ne devrait pas pouvoir l'invalider. Le
 * paramètre `lld_breaks_chain` perd donc son sens · il créait des faux
 * négatifs systémiques (cas ECO 2024 · LCD Nevada → LLD Toyota
 * intercalé → LCD Nevada, chaîne légitime cassée à tort).
 *
 * L'algorithme `RiskDetectionService::buildChains()` ignore désormais
 * silencieusement tous les LLD lors de la construction des chaînes.
 *
 * **`down()` recrée** la colonne avec default `false` (cohérent doctrine
 * actuelle si rollback · les LLD intercalés ne brisent pas la chaîne).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fiscal_risk_settings', function (Blueprint $table): void {
            $table->dropColumn('lld_breaks_chain');
        });
    }

    public function down(): void
    {
        Schema::table('fiscal_risk_settings', function (Blueprint $table): void {
            $table->boolean('lld_breaks_chain')->default(false)->after('count_high');
        });
    }
};
