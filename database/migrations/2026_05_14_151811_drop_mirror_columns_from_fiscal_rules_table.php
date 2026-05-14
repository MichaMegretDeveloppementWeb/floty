<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 13 D5.14 · ADR-0022 finalisée v1.4 · `fiscal_rules` devient
 * un index strictement minimal.
 *
 * **Doctrine** · les classes PHP des règles fiscales sont la source
 * de vérité unique. La table `fiscal_rules` ne porte plus que
 * l'index nécessaire pour relier l'id BDD à la classe PHP. Toutes
 * les autres colonnes étaient des miroirs des données vivant dans
 * les classes et ne sont plus consommées par le code applicatif
 * depuis D5.13 (page Règles) et D5.14 (breakdowns véhicule/contrat).
 *
 * **Colonnes conservées** ·
 *   - `id` (PK · index stable, référençable par FK potentielles)
 *   - `rule_code` (clé business · format `R-{YYYY}-{nnn}`)
 *   - `fiscal_year` (filtre par année)
 *   - `code_reference` (chemin de la classe PHP qui porte la règle)
 *   - timestamps (audit du seed)
 *
 * **Colonnes droppées** · name, description, rule_type, taxes_concerned,
 * applicability_start, applicability_end, vehicle_characteristics_consumed,
 * vehicle_characteristics_produced, legal_basis, pedagogical_content,
 * display_order, is_active.
 *
 * Les CHECK constraints associés sont également droppés.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            $this->dropCheckIfExists('chk_fiscal_rules_applicability_dates_ordered');
            $this->dropCheckIfExists('chk_fiscal_rules_type_enum');
        }

        Schema::table('fiscal_rules', function (Blueprint $table) {
            $table->dropIndex(['fiscal_year', 'display_order']);
            $table->dropIndex(['fiscal_year', 'rule_type']);
            $table->dropIndex(['is_active', 'fiscal_year']);

            $table->dropColumn([
                'name',
                'description',
                'rule_type',
                'taxes_concerned',
                'applicability_start',
                'applicability_end',
                'vehicle_characteristics_consumed',
                'vehicle_characteristics_produced',
                'legal_basis',
                'pedagogical_content',
                'display_order',
                'is_active',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('fiscal_rules', function (Blueprint $table) {
            $table->string('name')->after('rule_code');
            $table->text('description')->after('name');
            $table->string('rule_type', 20)->after('fiscal_year');
            $table->json('taxes_concerned')->after('rule_type');
            $table->date('applicability_start')->after('taxes_concerned');
            $table->date('applicability_end')->nullable()->after('applicability_start');
            $table->json('vehicle_characteristics_consumed')->nullable()->after('applicability_end');
            $table->json('vehicle_characteristics_produced')->nullable()->after('vehicle_characteristics_consumed');
            $table->json('legal_basis')->nullable()->after('vehicle_characteristics_produced');
            $table->json('pedagogical_content')->nullable()->after('legal_basis');
            $table->unsignedSmallInteger('display_order')->default(0)->after('code_reference');
            $table->boolean('is_active')->default(true)->after('display_order');

            $table->index(['fiscal_year', 'display_order']);
            $table->index(['fiscal_year', 'rule_type']);
            $table->index(['is_active', 'fiscal_year']);
        });
    }

    private function dropCheckIfExists(string $name): void
    {
        $exists = DB::selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
            ['fiscal_rules', $name],
        );

        if (((int) ($exists->c ?? 0)) > 0) {
            DB::statement(sprintf('ALTER TABLE fiscal_rules DROP CHECK %s', $name));
        }
    }
};
