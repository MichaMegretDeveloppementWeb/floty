<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `drivers` - Conducteurs, rattachés à une company unique.
 *
 * Cf. 01-schema-metier.md § 5.
 *
 * Fonctionnalité « Remplacer par… » (phase 06) : bulk UPDATE
 * `contracts.driver_id` côté applicatif, pas de structure dédiée en base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')
                ->constrained('companies')
                ->restrictOnDelete();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'is_active']);
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};
