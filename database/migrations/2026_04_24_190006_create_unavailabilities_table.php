<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Table `unavailabilities` · plages continues durant lesquelles un véhicule
 * n'est pas attribuable.
 *
 * Cf. 01-schema-metier.md § 7 + ADR-0016 rev. 1.1 (9 cases UnavailabilityType).
 *
 * Modélisation en **plages** (`start_date` / `end_date`) plutôt qu'une
 * ligne par jour, justifiée par ·
 *   - une indispo = un événement (passage en maintenance) pas 10 jours distincts
 *   - plus compact en base
 *   - projection plage → jours triviale côté code fiscal
 *
 * Trois types ont un **impact fiscal réducteur** (R-2024-008) ·
 * `accident_no_circulation`, `pound_public`, `ci_suspension`. La
 * dénormalisation `has_fiscal_impact` accélère les requêtes fiscales ;
 * un CHECK garantit la cohérence.
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
        });

        // CHECK constraints · filet SQL défensif, MySQL uniquement
        // (SQLite ne supporte pas `ALTER TABLE ... ADD CONSTRAINT`).
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
