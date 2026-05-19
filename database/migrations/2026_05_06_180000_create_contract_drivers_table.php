<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot contract_drivers: a contract can have 0, 1 or many drivers.
 * Drops contracts.driver_id in favour of the pivot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_drivers', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('contract_id')
                ->constrained('contracts')
                ->cascadeOnDelete();
            $table->foreignId('driver_id')
                ->constrained('drivers')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['contract_id', 'driver_id']);
            $table->index('driver_id');
        });

        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropForeign(['driver_id']);
            $table->dropColumn('driver_id');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->foreignId('driver_id')
                ->nullable()
                ->after('company_id')
                ->constrained('drivers')
                ->nullOnDelete();
        });

        Schema::dropIfExists('contract_drivers');
    }
};
