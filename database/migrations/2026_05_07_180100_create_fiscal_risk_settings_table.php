<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fiscal risk detection thresholds (ADR-0015 § D7 rev. 1.1).
 * Application-level singleton at id=1, with conservative defaults editable from settings.
 * Fields: max_interval (LCD chain gap), threshold_low / threshold_high (cumulative days),
 * count_high (contract count alternative).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_risk_settings', function (Blueprint $table): void {
            $table->id();

            $table->unsignedTinyInteger('max_interval')->default(15);
            $table->unsignedSmallInteger('threshold_low')->default(30);
            $table->unsignedSmallInteger('threshold_high')->default(90);
            $table->unsignedTinyInteger('count_high')->default(5);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_risk_settings');
    }
};
