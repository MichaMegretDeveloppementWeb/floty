<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-cluster fiscal review decisions (ADR-0015 § 5.2).
 * Keyed by (company_id, fiscal_year, cluster_fingerprint) so decisions outlive
 * a specific declaration and can be auto-reused on regeneration when the cluster
 * fingerprint is unchanged. decision: conserved | requalified.
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
