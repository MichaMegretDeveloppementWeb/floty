<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 13 D5.10.S · ajoute `excluded_contract_ids` à
 * `fiscal_review_decisions`.
 *
 * Permet à l'utilisateur d'exclure individuellement certains contrats
 * d'un cluster détecté · ils sont alors traités comme des LCD
 * individuels exemptés R-2024-021 et ne participent pas à l'opt-out
 * en cas de décision Requalified. Le cluster effectif (stats, opt-outs,
 * snapshot) est recalculé en excluant ces contrats.
 *
 * Default null = aucune exclusion (comportement antérieur préservé
 * pour les décisions existantes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fiscal_review_decisions', function (Blueprint $table): void {
            $table->json('excluded_contract_ids')->nullable()->after('justification');
        });
    }

    public function down(): void
    {
        Schema::table('fiscal_review_decisions', function (Blueprint $table): void {
            $table->dropColumn('excluded_contract_ids');
        });
    }
};
