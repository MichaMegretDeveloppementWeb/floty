<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Unavailability ranges (ADR-0016 rev. 1.1, R-2024-008).
 * Three types trigger a fiscal reduction: accident_no_circulation, pound_public, ci_suspension.
 * has_fiscal_impact is denormalised and kept consistent via CHECK constraint.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unavailabilities', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('vehicle_id')
                ->constrained('vehicles')
                ->restrictOnDelete();

            $table->enum('type', [
                'maintenance',
                'technical_inspection',
                'accident_repair',
                'accident_no_circulation',
                'pound_public',
                'pound_private',
                'ci_suspension',
                'theft',
                'other',
            ]);
            $table->boolean('has_fiscal_impact');

            $table->date('start_date');
            $table->date('end_date')->nullable();

            $table->text('description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['vehicle_id', 'start_date']);
            $table->index(['vehicle_id', 'has_fiscal_impact', 'start_date']);
            $table->index(['type', 'start_date']);
            // Index on deleted_at: SoftDeletes scopes would otherwise scan the full table.
            $table->index('deleted_at', 'unavailabilities_deleted_at_idx');
        });

        // CHECK constraints: MySQL only.
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE unavailabilities
                ADD CONSTRAINT chk_unavailabilities_dates_ordered
                CHECK (end_date IS NULL OR start_date <= end_date)
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE unavailabilities
                ADD CONSTRAINT chk_unavailabilities_type_enum
                CHECK (type IN (
                    'maintenance',
                    'technical_inspection',
                    'accident_repair',
                    'accident_no_circulation',
                    'pound_public',
                    'pound_private',
                    'ci_suspension',
                    'theft',
                    'other'
                ))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE unavailabilities
                ADD CONSTRAINT chk_unavailabilities_fiscal_impact_consistent
                CHECK (has_fiscal_impact = (type IN (
                    'accident_no_circulation',
                    'pound_public',
                    'ci_suspension'
                )))
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('unavailabilities');
    }
};
