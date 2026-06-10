<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Serves the `SELECT DISTINCT garage` autosuggestion as an index-only scan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_events', function (Blueprint $table): void {
            $table->index('garage');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_events', function (Blueprint $table): void {
            $table->dropIndex(['garage']);
        });
    }
};
