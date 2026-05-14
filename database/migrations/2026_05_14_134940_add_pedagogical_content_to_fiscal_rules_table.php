<?php

declare(strict_types=1);

use App\Fiscal\ValueObjects\RulePedagogicalContent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 13 D5.12 · ADR-0022 finalisée v1.2.
 *
 * Ajoute une colonne JSON `pedagogical_content` à `fiscal_rules`
 * pour stocker la projection sérialisée du VO PHP
 * {@see RulePedagogicalContent}.
 *
 * Avant cette phase, le contenu pédagogique des règles vivait dans
 * `resources/js/data/fiscalRulesContent.ts` (couche TS séparée),
 * violation doctrine « 1 règle = 1 source ». Désormais le contenu
 * vit dans chaque classe Rule PHP (méthode `pedagogicalContent()`),
 * et est projeté dans cette colonne par le seeder pour alimenter le
 * DTO consommé par la page « Règles de calcul ».
 *
 * Nullable parce que la migration s'applique avant le reseed · le
 * seeder remplira la colonne ensuite. Une fois la valeur posée, elle
 * n'est jamais null en production (cf. invariant de la doctrine).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fiscal_rules', function (Blueprint $table) {
            $table->json('pedagogical_content')->nullable()->after('legal_basis');
        });
    }

    public function down(): void
    {
        Schema::table('fiscal_rules', function (Blueprint $table) {
            $table->dropColumn('pedagogical_content');
        });
    }
};
