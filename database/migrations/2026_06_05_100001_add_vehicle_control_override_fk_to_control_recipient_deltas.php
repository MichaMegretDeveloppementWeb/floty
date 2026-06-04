<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier B / B2 · wires the deferred foreign key from the B1 recipient deltas
 * (`control_recipient_deltas.vehicle_control_override_id`, created column-only in
 * B1) to the new `vehicle_control_overrides` table. Additive and safe: B1 only
 * ever wrote `settings` / `definition` level deltas, so every existing row has a
 * NULL `vehicle_control_override_id` and none violates the new constraint.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('control_recipient_deltas', function (Blueprint $table): void {
            $table->foreign('vehicle_control_override_id')
                ->references('id')
                ->on('vehicle_control_overrides')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('control_recipient_deltas', function (Blueprint $table): void {
            $table->dropForeign(['vehicle_control_override_id']);
        });
    }
};
