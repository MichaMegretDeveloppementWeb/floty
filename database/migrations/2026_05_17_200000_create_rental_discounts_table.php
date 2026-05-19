<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rental discounts: (company × period × percentage). Percentage stored in basis points
 * (1 bp = 0.01 %, range 1..10000). Non-overlap is validated in RentalDiscountConflictService
 * (the rule depends on the vehicle pivot intersection).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_discounts', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->restrictOnDelete();

            // Inclusive period.
            $table->date('start_date');
            $table->date('end_date');

            // Basis points (1..10000 = 0.01 %..100 %).
            $table->unsignedSmallInteger('discount_basis_points');

            $table->string('label', 120)->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'start_date', 'end_date'], 'rd_company_period_idx');
        });

        // CHECK constraints: MySQL only.
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE rental_discounts
                ADD CONSTRAINT chk_rd_end_after_start
                CHECK (end_date >= start_date)
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE rental_discounts
                ADD CONSTRAINT chk_rd_bp_range
                CHECK (discount_basis_points BETWEEN 1 AND 10000)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_discounts');
    }
};
