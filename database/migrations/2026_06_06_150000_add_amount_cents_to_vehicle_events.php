<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Carnet de dépenses · `amount_cents` is the optional COST (in cents, TTC)
 * attached to a vehicle event (maintenance, control execution, fleet exit,
 * acquisition price, ...). Costs only · never a revenue. Nullable: an event
 * may carry no cost. Drives the per-vehicle and global "Total" of filtered
 * events. No fiscal/billing impact. Additive, nullable, unsigned (no negative)
 * · safe on prod, no backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_events', function (Blueprint $table): void {
            $table->unsignedBigInteger('amount_cents')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_events', function (Blueprint $table): void {
            $table->dropColumn('amount_cents');
        });
    }
};
