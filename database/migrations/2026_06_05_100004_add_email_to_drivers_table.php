<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier B / B2 · adds a nullable `email` to drivers so a control flagged
 * "prévenir le conducteur" can notify the vehicle's driver (Chantier B3 resolves
 * the active driver and sends). Additive, nullable, no backfill. Not unique: two
 * drivers may legitimately share a contact email.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table): void {
            $table->string('email', 180)->nullable()->after('last_name');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table): void {
            $table->dropIndex(['email']);
            $table->dropColumn('email');
        });
    }
};
