<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invoice lines: one row per vehicle per invoiced month. Fully snapshotted
 * (label, days_used, optimal rate breakdown, unit rates, total) so upstream
 * mutations cannot corrupt issued invoices.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_lines', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('invoice_id')
                ->constrained('invoices')
                ->cascadeOnDelete();

            $table->foreignId('vehicle_id')
                ->constrained('vehicles')
                ->restrictOnDelete();

            $table->string('vehicle_label_snapshot', 128);

            $table->unsignedSmallInteger('days_used');

            $table->unsignedTinyInteger('months_billed');
            $table->unsignedTinyInteger('weeks_billed');
            $table->unsignedTinyInteger('days_billed');

            // Snapshot rates in cents.
            $table->unsignedInteger('daily_rate_cents');
            $table->unsignedInteger('weekly_rate_cents');
            $table->unsignedInteger('monthly_rate_cents');

            $table->unsignedInteger('total_ht_cents');

            $table->timestamps();

            $table->index('invoice_id', 'invl_invoice_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
    }
};
