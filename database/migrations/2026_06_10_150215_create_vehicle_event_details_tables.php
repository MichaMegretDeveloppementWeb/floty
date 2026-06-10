<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Section « Details » des evenements vehicule :
 *   - `vehicle_event_details` : une ligne de detail libre par row (ex.
 *     « Vidange », « Changement courroie »), rattachee a son evenement,
 *     dedupliquee intra-evenement (UNIQUE) ;
 *   - `vehicle_event_detail_suggestions` : catalogue d'autocompletion gere
 *     par l'utilisateur (« Ajouter a la liste » \ retrait), sans bloc fige.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_event_details', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vehicle_event_id')
                ->constrained('vehicle_events')
                ->cascadeOnDelete();
            $table->string('detail', 100);
            $table->timestamps();

            $table->unique(['vehicle_event_id', 'detail'], 'ved_event_detail_unique');
        });

        Schema::create('vehicle_event_detail_suggestions', function (Blueprint $table): void {
            $table->id();
            $table->string('label', 100)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_event_detail_suggestions');
        Schema::dropIfExists('vehicle_event_details');
    }
};
