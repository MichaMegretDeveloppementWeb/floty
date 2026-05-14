<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `fiscal_rules` · index strictement minimal des règles fiscales
 * (ADR-0022 v1.4, Phase 13 D5.14).
 *
 * Cf. 02-schema-fiscal.md § 1 + ADR-0002 + ADR-0006 + ADR-0009 + ADR-0022.
 *
 * **Doctrine** · les classes PHP des règles sont la source de vérité
 * unique · cette table ne porte plus que l'index nécessaire pour relier
 * l'id BDD à la classe PHP. Toutes les autres données vivent dans les
 * classes (nom, description, base légale, contenu pédagogique, etc.)
 * et sont projetées à la volée par le registry. Alimentée exclusivement
 * par seeders (ADR-0002).
 *
 * **Pas de versioning** (ADR-0009) · si une règle est erronée, on corrige
 * directement sa classe PHP, le `rule_code` en base reste stable. Aucune
 * colonne `version_internal`. L'historique des corrections vit dans
 * `git log` et les sections « Révisions » de `taxes-rules/{year}.md`.
 *
 * Jamais de suppression · les ids restent référencés par les snapshots
 * historiques de déclarations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_rules', function (Blueprint $table): void {
            $table->id();

            $table->string('rule_code', 20);
            $table->unsignedSmallInteger('fiscal_year');
            $table->string('code_reference', 500);

            $table->timestamps();

            $table->unique(['rule_code', 'fiscal_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_rules');
    }
};
