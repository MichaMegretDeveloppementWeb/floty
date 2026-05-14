<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Table `fiscal_review_decisions` · décisions humaines de revue
 * fiscale par cluster (Phase 11 D1, ADR-0015 § 5.2).
 *
 * Une décision est identifiée par le triplet `(company_id, fiscal_year,
 * cluster_fingerprint)`. Le `cluster_fingerprint` est un sha256 stable
 * calculé sur les contrats du cluster (ADR-0015 § 4) qui permet de
 * persister la décision indépendamment des déclarations elles-mêmes :
 *   - À la régénération d'une déclaration obsolète, les décisions dont
 *     le fingerprint est inchangé sont auto-reprises (l'utilisateur ne
 *     ré-évalue que les clusters nouveaux ou modifiés).
 *   - Pas de FK directe vers `fiscal_declarations` car le couple
 *     fingerprint+(company,year) suffit à identifier la décision.
 *
 * `decision` :
 *   - `conserved` : l'utilisateur conserve l'exonération LCD.
 *   - `requalified` : l'utilisateur requalifie les contrats du cluster
 *     comme LLD (ils perdent l'exonération individuellement).
 *
 * `justification` est optionnelle pour le niveau moyen, requise pour
 * le niveau élevé (validation côté Action D3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_review_decisions', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->restrictOnDelete();

            $table->unsignedSmallInteger('fiscal_year');

            $table->string('risk_code', 32);
            $table->char('cluster_fingerprint', 64);
            $table->string('decision', 16);
            $table->text('justification')->nullable();
            $table->json('excluded_contract_ids')->nullable();

            $table->foreignId('decided_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('decided_at');

            $table->timestamps();

            $table->unique(['company_id', 'fiscal_year', 'cluster_fingerprint'], 'review_company_year_fp_uk');
            $table->index(['company_id', 'fiscal_year'], 'review_company_year_idx');
        });

        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE fiscal_review_decisions ADD CONSTRAINT chk_review_decision_enum CHECK (decision IN ('conserved', 'requalified'))");
        DB::statement("ALTER TABLE fiscal_review_decisions ADD CONSTRAINT chk_review_risk_code_enum CHECK (risk_code IN ('R-LCD-CHAIN', 'R-LCD-CHAIN-FORT'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_review_decisions');
    }
};
