<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `assignments` — Entité pivot centrale.
 *
 * Cf. 01-schema-metier.md § 6 + ADR-0005 (calcul jour par jour).
 *
 * **Une ligne = (véhicule, jour)**. Granularité jour, année civile.
 *
 * Contrainte critique : **un véhicule ne peut être attribué qu'à une seule
 * entreprise sur un jour donné** (CDC § 2.4). Implémentée par colonne
 * générée `vehicle_date_active` + UNIQUE — l'index partiel n'existe pas
 * nativement sous MySQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('vehicle_id')
                ->constrained('vehicles')
                ->restrictOnDelete();
            $table->foreignId('company_id')
                ->constrained('companies')
                ->restrictOnDelete();
            $table->foreignId('driver_id')
                ->nullable()
                ->constrained('drivers')
                ->restrictOnDelete();

            $table->date('date');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'date']);
            $table->index(['vehicle_id', 'date']);
            $table->index('date');

            // Cumul LCD par couple sur l'année — index préfixé sur une
            // expression EXTRACT(YEAR). MySQL impose de passer par une
            // colonne générée stockée pour pouvoir indexer une expression.
            $table->unsignedSmallInteger('date_year')
                ->storedAs('YEAR(`date`)');
            $table->index(['vehicle_id', 'company_id', 'date_year']);

            // UNIQUE (vehicle_id, date) direct — fallback MVP sans colonne
            // générée filtrée par soft delete. Conséquence : une attribution
            // soft-deletée verrouille le slot (vehicle_id, date) et empêche
            // la re-création. À revoir en V1 via triggers.
            $table->unique(['vehicle_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
