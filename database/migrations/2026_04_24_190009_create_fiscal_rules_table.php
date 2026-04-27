<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Table `fiscal_rules` — Index consultable des règles fiscales.
 *
 * Cf. 02-schema-fiscal.md § 1 + ADR-0002 + ADR-0006 + ADR-0009.
 *
 * Cette table ne porte **pas la logique** (ADR-0006 § 3 : logique en code,
 * métadonnées en base). Alimentée exclusivement par seeders (ADR-0002).
 *
 * **Pas de versioning** (ADR-0009) : si une règle est erronée, on corrige
 * directement sa classe PHP, le `rule_code` en base reste stable. Aucune
 * colonne `version_internal`. L'historique des corrections vit dans
 * `git log` et les sections « Révisions » de `taxes-rules/{year}.md`.
 *
 * Jamais de suppression : les règles désactivées conservent `is_active = false`
 * pour rester référencées par les snapshots historiques.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_rules', function (Blueprint $table): void {
            $table->id();

            $table->string('rule_code', 20);
            $table->string('name');
            $table->text('description');

            $table->unsignedSmallInteger('fiscal_year');
            $table->string('rule_type', 20);
            $table->json('taxes_concerned');

            $table->date('applicability_start');
            $table->date('applicability_end')->nullable();

            $table->json('vehicle_characteristics_consumed')->nullable();
            $table->json('vehicle_characteristics_produced')->nullable();
            $table->json('legal_basis');

            $table->string('code_reference', 500);
            $table->unsignedSmallInteger('display_order');
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['rule_code', 'fiscal_year']);
            $table->index(['fiscal_year', 'display_order']);
            $table->index(['fiscal_year', 'rule_type']);
            $table->index(['is_active', 'fiscal_year']);
        });

        // CHECK constraints — filet SQL défensif, MySQL uniquement
        // (SQLite ne supporte pas `ALTER TABLE ... ADD CONSTRAINT`).
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE fiscal_rules
                ADD CONSTRAINT chk_fiscal_rules_applicability_dates_ordered
                CHECK (applicability_end IS NULL OR applicability_start <= applicability_end)
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE fiscal_rules
                ADD CONSTRAINT chk_fiscal_rules_type_enum
                CHECK (rule_type IN ('classification', 'tariff', 'exemption', 'abatement', 'transversal'))
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_rules');
    }
};
