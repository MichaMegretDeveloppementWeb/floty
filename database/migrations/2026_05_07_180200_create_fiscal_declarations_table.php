<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fiscal declarations root entity (ADR-0015 § 5.1 rev. 1.1).
 * Status: draft, deferred, generated. Obsolescence via is_obsolete + superseded_by_id.
 * At most one active (is_obsolete=false) per (company, fiscal_year) — enforced applicatively
 * and by a later partial-unique migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_declarations', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->restrictOnDelete();

            $table->unsignedSmallInteger('fiscal_year');
            $table->string('reference', 32)->nullable();

            $table->string('status', 16)->default('draft');

            $table->timestamp('generated_at')->nullable();
            $table->string('generated_pdf_path', 255)->nullable();
            $table->string('generated_pdf_hash', 64)->nullable();
            $table->json('generated_snapshot_payload')->nullable();

            $table->boolean('is_obsolete')->default(false);
            $table->timestamp('obsolete_at')->nullable();
            $table->json('obsolete_reasons')->nullable();

            $table->foreignId('superseded_by_id')
                ->nullable()
                ->constrained('fiscal_declarations')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'fiscal_year', 'is_obsolete'], 'decl_company_year_active_idx');
            $table->index('status', 'decl_status_idx');
            $table->index('superseded_by_id', 'decl_superseded_by_idx');
            $table->index(['company_id', 'fiscal_year', 'reference'], 'decl_company_year_reference_idx');
        });

        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE fiscal_declarations ADD CONSTRAINT chk_decl_year_range CHECK (fiscal_year BETWEEN 2020 AND 2099)');
        DB::statement("ALTER TABLE fiscal_declarations ADD CONSTRAINT chk_decl_status_enum CHECK (status IN ('draft', 'deferred', 'generated'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_declarations');
    }
};
