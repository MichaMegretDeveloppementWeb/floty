<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fiscal rules index (ADR-0002, ADR-0006, ADR-0009, ADR-0022).
 * Minimal table: PHP rule classes are the source of truth; this only links id to rule_code.
 * Seeder-fed, no versioning, no deletion (ids referenced by declaration snapshots).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_rules', function (Blueprint $table): void {
            $table->id();

            $table->string('rule_code', 20);
            $table->unsignedSmallInteger('fiscal_year');
            $table->string('code_reference', 500);

            $table->timestamps();

            $table->unique(['rule_code', 'fiscal_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_rules');
    }
};
