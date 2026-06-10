<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogue of event natures (refonte type → nature). One row per suggested
 * nature label; `is_fiscally_reductive` marks the frozen reductive block
 * (seeded by VehicleEventNatureSeeder, no admin UI). User entries added via
 * « Ajouter à la liste » land here as non-reductive suggestions.
 *
 * The label length (60) mirrors `vehicle_event_categories.category`, where
 * the natures attached to an event are stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_event_natures', function (Blueprint $table): void {
            $table->id();
            $table->string('label', 60)->unique();
            $table->boolean('is_fiscally_reductive')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_event_natures');
    }
};
