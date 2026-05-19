<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Driver to Company refactor to N:N via driver_company pivot (joined_at, left_at).
 * Deactivation per company is expressed by setting left_at on the pivot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_company', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('driver_id')
                ->constrained('drivers')
                ->cascadeOnDelete();
            $table->foreignId('company_id')
                ->constrained('companies')
                ->restrictOnDelete();
            $table->date('joined_at');
            $table->date('left_at')->nullable();
            $table->timestamps();

            $table->unique(['driver_id', 'company_id', 'joined_at'], 'driver_company_unique');
            $table->index(['company_id', 'left_at']);
            $table->index(['driver_id', 'left_at']);
        });

        Schema::table('drivers', function (Blueprint $table): void {
            $table->dropForeign(['company_id']);
            $table->dropIndex(['company_id', 'is_active']);
            $table->dropColumn(['company_id', 'is_active', 'deactivated_at']);
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table): void {
            $table->foreignId('company_id')
                ->nullable()
                ->after('id')
                ->constrained('companies')
                ->restrictOnDelete();
            $table->boolean('is_active')->default(true)->after('last_name');
            $table->timestamp('deactivated_at')->nullable()->after('is_active');
            $table->index(['company_id', 'is_active']);
        });

        Schema::dropIfExists('driver_company');
    }
};
