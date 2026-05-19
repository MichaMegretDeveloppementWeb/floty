<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Monthly invoices per company (immutable snapshot: invoice_number, total, pdf path + hash).
 * is_divergent is set by observers on mutations. superseded_by_id + obsolete_at
 * track the regeneration chain.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->restrictOnDelete();

            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');

            // Format: {year}-{month:02d}-{seq:04d}.
            $table->string('invoice_number', 32);

            $table->unsignedInteger('total_ht_cents');

            $table->boolean('is_divergent')->default(false);

            $table->foreignId('superseded_by_id')
                ->nullable()
                ->constrained('invoices')
                ->nullOnDelete();
            $table->timestamp('obsolete_at')->nullable();

            $table->string('pdf_path', 255);
            // SHA-256 hex = 64 chars.
            $table->string('pdf_hash', 64);

            $table->timestamp('generated_at');
            $table->foreignId('generated_by_user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->unique('invoice_number');

            $table->index(['company_id', 'year', 'month'], 'inv_company_year_month_idx');
            $table->index('is_divergent', 'inv_is_divergent_idx');
            $table->index('superseded_by_id', 'inv_superseded_by_idx');
        });

        // CHECK constraints: MySQL only.
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE invoices
                ADD CONSTRAINT chk_inv_year_range
                CHECK (year BETWEEN 2020 AND 2099)
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE invoices
                ADD CONSTRAINT chk_inv_month_range
                CHECK (month BETWEEN 1 AND 12)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
