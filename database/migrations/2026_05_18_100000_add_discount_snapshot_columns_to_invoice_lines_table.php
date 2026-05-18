<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration additive sur `invoice_lines` (lot 3 du chantier RentalDiscount) ·
 * snapshot du libellé et du pourcentage de la réduction commerciale
 * appliquée au moment de l'émission de la facture.
 *
 * **Pourquoi un snapshot et pas une jointure sur `applied_discount_id`** ·
 *   - La doctrine immuabilité (ADR-0008) impose qu'une facture émise
 *     ne change jamais. Si l'utilisateur renomme une réduction (typo
 *     corrigée, refonte du label commercial), les factures historiques
 *     doivent continuer à afficher l'ancien libellé exact.
 *   - Aligné avec `vehicle_label_snapshot` qui suit le même pattern.
 *
 * **Coexistence avec `applied_discount_id`** · la FK sert à naviguer
 * (lien Show réduction) tant que la réduction existe encore. Si elle
 * est soft-deletée, la FK reste valide. Si elle est hard-deletée (rare,
 * cas cleanup admin), la FK bascule à NULL et seul le snapshot reste ·
 * suffisant pour le PDF historique.
 *
 * Champs nullable · NULL ssi la ligne n'a pas eu de réduction appliquée
 * (cas dominant, alignement sémantique avec `applied_discount_id`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table): void {
            // Libellé snapshot · max 120 caractères, aligné avec la
            // contrainte de `rental_discounts.label`.
            $table->string('applied_discount_label_snapshot', 120)
                ->nullable()
                ->after('applied_discount_id');

            // Pourcentage snapshot en basis points (cohérent avec
            // `rental_discounts.discount_basis_points` · 1..10000).
            $table->unsignedSmallInteger('applied_discount_basis_points_snapshot')
                ->nullable()
                ->after('applied_discount_label_snapshot');
        });

        // Pas de backfill nécessaire · les lignes existantes ont toutes
        // `applied_discount_id = NULL` (Lot 1 backfill garantit cela).
        // Cohérence sémantique · pas de réduction = pas de snapshot.
    }

    public function down(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table): void {
            $table->dropColumn([
                'applied_discount_label_snapshot',
                'applied_discount_basis_points_snapshot',
            ]);
        });
    }
};
