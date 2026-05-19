<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot rental_discount_vehicles. Empty pivot for a discount means it applies to
 * every vehicle of the company over the period (resolved in DiscountResolver).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_discount_vehicles', function (Blueprint $table): void {
            $table->foreignId('rental_discount_id')
                ->constrained('rental_discounts')
                ->cascadeOnDelete();

            $table->foreignId('vehicle_id')
                ->constrained('vehicles')
                ->restrictOnDelete();

            $table->primary(['rental_discount_id', 'vehicle_id'], 'rdv_pk');
            $table->index('vehicle_id', 'rdv_vehicle_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_discount_vehicles');
    }
};
