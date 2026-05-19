<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invoice issuer (billing settings). Application-level singleton: a single row at id=1.
 * All fields are nullable; the user fills them in via the settings page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_settings', function (Blueprint $table): void {
            $table->id();

            $table->string('name', 128)->nullable();
            $table->string('address_line_1', 128)->nullable();
            $table->string('address_line_2', 128)->nullable();
            $table->string('postal_code', 16)->nullable();
            $table->string('city', 64)->nullable();
            $table->string('siren', 14)->nullable();
            $table->string('contact_email', 128)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_settings');
    }
};
