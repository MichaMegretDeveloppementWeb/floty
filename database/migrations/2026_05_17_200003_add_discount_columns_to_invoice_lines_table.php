<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration additive sur `invoice_lines` · ajoute le snapshot ligne par
 * ligne de la réduction commerciale appliquée (lot 1 du chantier
 * RentalDiscount).
 *
 * **Doctrine immuabilité préservée** · `total_ht_cents` reste le NET
 * (= gross - discount), 100 % rétro-compat. Les nouveaux champs sont
 * additifs.
 *
 * **`applied_discount_id` est `nullOnDelete`** · si la réduction parent
 * est soft-deletée (cas normal) la FK reste valide car soft-delete ne
 * supprime pas la row. Si jamais une hard suppression a lieu (cas
 * exceptionnel de cleanup admin), on bascule à null plutôt que de
 * casser la facture émise.
 *
 * **Backfill** · pour les lignes existantes (pré-feature) ·
 *   - `gross_total_cents = total_ht_cents` (= net = pas de réduction)
 *   - `discount_cents = 0`
 *   - `applied_discount_id = NULL`
 *
 * Vérifié par un test feature dédié au lot 2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table): void {
            // Total ligne BRUT avant application de la réduction.
            // Snapshot figé.
            //
            // Default 0 · rétro-compat pour le code de génération (Lot 1)
            // qui sera mis à jour au Lot 2 pour fournir explicitement
            // `gross_total_cents = total_ht_cents` (sans réduction) ou la
            // valeur brute calculée (avec réduction). Le backfill SQL en
            // up() met la vraie valeur pour les lignes existantes.
            $table->unsignedInteger('gross_total_cents')->default(0)->after('total_ht_cents');

            // Montant de la réduction appliquée sur cette ligne (cents).
            // Snapshot figé.
            $table->unsignedInteger('discount_cents')->default(0)->after('gross_total_cents');

            // Référence vers la réduction commerciale appliquée
            // (preserved via nullOnDelete · soft-delete normal n'impacte
            // pas la FK).
            $table->foreignId('applied_discount_id')
                ->nullable()
                ->after('discount_cents')
                ->constrained('rental_discounts')
                ->nullOnDelete();
        });

        // Backfill explicite pour les lignes existantes · gross = net,
        // discount = 0, applied_discount_id = null par défaut.
        DB::statement('UPDATE invoice_lines SET gross_total_cents = total_ht_cents');
    }

    public function down(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table): void {
            $table->dropForeign(['applied_discount_id']);
            $table->dropColumn(['gross_total_cents', 'discount_cents', 'applied_discount_id']);
        });
    }
};
