<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration additive sur `invoices` · ajoute le snapshot agrégat des
 * réductions commerciales appliquées à la facture (lot 1 du chantier
 * RentalDiscount).
 *
 * **Doctrine immuabilité préservée** · ces colonnes sont snapshot au
 * moment de l'émission. `total_ht_cents` reste le NET (= ce que paie
 * l'entreprise = gross - discount). 100 % rétro-compat sur les
 * consommateurs existants.
 *
 * **Backfill** · les factures existantes (pré-feature) sont initialisées
 * avec `total_gross_cents = total_ht_cents` et `total_discount_cents = 0`
 * · sémantique « ces factures n'ont jamais eu de réduction ». Vérifié
 * par un test feature dédié au lot 2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            // Total HT BRUT avant application des réductions commerciales.
            // Snapshot figé à l'émission, ne suit pas les changements de
            // tarif ni de réduction ultérieurs.
            //
            // Default 0 · rétro-compat pour le code de génération (Lot 1)
            // qui sera mis à jour au Lot 2 pour fournir explicitement
            // `total_gross_cents = total_ht_cents` (sans réduction) ou la
            // valeur brute calculée (avec réduction). Le backfill SQL en
            // up() met la vraie valeur pour les factures existantes.
            $table->unsignedInteger('total_gross_cents')->default(0)->after('total_ht_cents');

            // Somme des réductions commerciales appliquées sur les lignes
            // de la facture. Snapshot figé.
            $table->unsignedInteger('total_discount_cents')->default(0)->after('total_gross_cents');
        });

        // Backfill explicite pour les factures existantes (avant la
        // feature) · gross = net = total_ht_cents, discount = 0.
        DB::statement('UPDATE invoices SET total_gross_cents = total_ht_cents');
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn(['total_gross_cents', 'total_discount_cents']);
        });
    }
};
