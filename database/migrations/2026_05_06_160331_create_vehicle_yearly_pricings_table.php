<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Yearly pricing per vehicle (daily/weekly/monthly rates in cents).
 * UNIQUE(vehicle_id, year) supports idempotent upsert. No intra-year history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_yearly_pricings', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('vehicle_id')
                ->constrained('vehicles')
                ->restrictOnDelete();

            $table->unsignedSmallInteger('year');

            // Rates in cents (100 = 1.00 EUR).
            $table->unsignedInteger('daily_rate_cents');
            $table->unsignedInteger('weekly_rate_cents');
            $table->unsignedInteger('monthly_rate_cents');

            $table->timestamps();

            $table->unique(['vehicle_id', 'year']);
        });

        // CHECK constraints: MySQL only.
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE vehicle_yearly_pricings
                ADD CONSTRAINT chk_vyp_year_range
                CHECK (year BETWEEN 2020 AND 2099)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_yearly_pricings');
    }
};
