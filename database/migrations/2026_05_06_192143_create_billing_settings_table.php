<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `billing_settings` · Émetteur de facture (Phase 14.G V1.2).
 *
 * Singleton applicatif : une seule ligne en base, identifiée par `id=1`.
 * Pas de FK ni de tenant · l'application est mono-société pour V1.2.
 *
 * Tous les champs sont nullable au démarrage : la première installation
 * crée la ligne via `firstOrCreate` avec des valeurs vides ; l'utilisateur
 * complète depuis la page Paramètres > Facturation.
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
