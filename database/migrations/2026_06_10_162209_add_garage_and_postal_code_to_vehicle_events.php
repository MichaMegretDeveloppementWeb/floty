<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional garage name and postal code on vehicle events.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_events', function (Blueprint $table): void {
            $table->string('garage', 120)->nullable()->after('description');
            $table->string('postal_code', 10)->nullable()->after('garage');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_events', function (Blueprint $table): void {
            $table->dropColumn(['garage', 'postal_code']);
        });
    }
};
