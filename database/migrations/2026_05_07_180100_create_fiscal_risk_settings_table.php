<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `fiscal_risk_settings` · seuils paramétrables de la grille de
 * détection des zones de risque fiscal (Phase 11 D1, ADR-0015 § D7
 * rev. 1.1).
 *
 * Singleton applicatif : une seule ligne identifiée par `id=1`. Mono
 * société pour V1.2. Les valeurs par défaut sont conservatoires (cf.
 * ADR-0015 § 9.4) : Renaud et son expert-comptable peuvent les ajuster
 * depuis la page « Paramètres · Détection de risque » sans déploiement.
 *
 * Sémantique des champs :
 *   - `max_interval` : nb max de jours pleins entre 2 contrats LCD pour
 *     les considérer comme chaînés (rattachés au même cluster).
 *   - `threshold_low` : cumul de jours au-delà duquel la chaîne est
 *     classée R-LCD-CHAIN (niveau moyen).
 *   - `threshold_high` : cumul de jours au-delà duquel la chaîne est
 *     classée R-LCD-CHAIN-FORT (niveau élevé).
 *   - `count_high` : nombre de contrats à partir duquel la chaîne est
 *     classée R-LCD-CHAIN-FORT (alternative au cumul).
 *
 * Doctrine D5.10.N (Phase 13) · les LLD sont ignorés lors de la
 * construction des chaînes LCD (la chaîne vise la continuité d'usage,
 * pas le véhicule). Le paramètre `lld_breaks_chain` qui contrôlait ce
 * comportement a été supprimé · `RiskDetectionService::buildChains()`
 * ignore désormais silencieusement tous les LLD.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_risk_settings', function (Blueprint $table): void {
            $table->id();

            $table->unsignedTinyInteger('max_interval')->default(15);
            $table->unsignedSmallInteger('threshold_low')->default(30);
            $table->unsignedSmallInteger('threshold_high')->default(90);
            $table->unsignedTinyInteger('count_high')->default(5);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_risk_settings');
    }
};
