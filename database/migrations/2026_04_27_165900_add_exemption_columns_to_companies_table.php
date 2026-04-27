<?php

declare(strict_types=1);

use App\Enums\Company\ExemptedActivity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1.9 — Ajout des marqueurs d'exonération côté entreprise
 * utilisatrice. Inactifs par défaut (toutes les entreprises Floty
 * actuelles sont des sociétés commerciales standards).
 *
 * - `is_oig` : organisme d'intérêt général au sens CGI 261, 7°
 *   (R-2024-018)
 * - `is_individual_business` : entreprise individuelle BIC/BNC en nom
 *   propre (R-2024-019)
 * - `exempted_activity` : activité exonérée éventuelle de l'entreprise
 *   (R-2024-022)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->boolean('is_oig')
                ->default(false)
                ->after('is_active');
            $table->boolean('is_individual_business')
                ->default(false)
                ->after('is_oig');
            $table->string('exempted_activity', 32)
                ->default(ExemptedActivity::None->value)
                ->after('is_individual_business');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn(['is_oig', 'is_individual_business', 'exempted_activity']);
        });
    }
};
