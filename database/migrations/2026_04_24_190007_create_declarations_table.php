<?php

declare(strict_types=1);

use App\Enums\Declaration\DeclarationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Table `declarations` — Déclaration fiscale annuelle par (company × année).
 *
 * Cf. 02-schema-fiscal.md § 2.
 *
 * Une déclaration = obligation fiscale d'une entreprise pour une année
 * civile. Porte :
 *   - un **statut** cycle de vie ({@see DeclarationStatus}),
 *   - un **drapeau d'invalidation** (ADR-0004) orthogonal au statut,
 *   - les totaux calculés (remplis après calcul fiscal — nullable tant que non calculé).
 *
 * Pas de soft delete : donnée fiscale persistante, jamais supprimée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('declarations', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->restrictOnDelete();

            $table->unsignedSmallInteger('fiscal_year');

            $table->string('status', 20)->default('draft');
            $table->timestamp('status_changed_at')->useCurrent();
            $table->foreignId('status_changed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->unsignedInteger('total_co2_tax')->nullable();
            $table->unsignedInteger('total_pollutant_tax')->nullable();
            $table->unsignedInteger('total_tax_all')->nullable();
            $table->timestamp('last_calculated_at')->nullable();

            $table->boolean('is_invalidated')->default(false);
            $table->timestamp('invalidated_at')->nullable();
            $table->string('invalidation_reason', 50)->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['company_id', 'fiscal_year']);
            $table->index(['fiscal_year', 'status']);
            $table->index(['is_invalidated', 'fiscal_year']);
        });

        // CHECK constraints — filet SQL défensif, MySQL uniquement
        // (SQLite ne supporte pas `ALTER TABLE ... ADD CONSTRAINT`).
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE declarations
                ADD CONSTRAINT chk_declarations_status_enum
                CHECK (status IN ('draft', 'verified', 'generated', 'sent'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE declarations
                ADD CONSTRAINT chk_declarations_invalidation_consistent
                CHECK (is_invalidated = 0 OR invalidated_at IS NOT NULL)
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE declarations
                ADD CONSTRAINT chk_declarations_invalidation_reason_enum
                CHECK (
                    invalidation_reason IS NULL
                    OR invalidation_reason IN (
                        'assignment_modified',
                        'vehicle_characteristics_changed',
                        'unavailability_changed',
                        'rule_changed',
                        'other'
                    )
                )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('declarations');
    }
};
