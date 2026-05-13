<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Traçabilité historique des factures · option C (cf. discussion D5.10.P).
 *
 * Ajoute trois colonnes pour gérer le cycle « régénération » sans perdre
 * la version précédente :
 *  - `deleted_at` : soft-delete (la facture obsolète reste en base, son
 *    PDF reste sur disque, audit trail légal préservé).
 *  - `superseded_by_id` : FK vers la facture qui remplace celle-ci · NULL
 *    pour la dernière version active. Permet de reconstituer la chaîne
 *    chronologique « v1 → v2 → v3 ».
 *  - `obsolete_at` : timestamp de l'obsolescence (instant exact où la
 *    facture a été soft-deletée par `RegenerateInvoiceAction`).
 *
 * Distinction avec `is_divergent` (Phase 14.R) :
 *  - `is_divergent` reste le flag instantané « le périmètre a évolué,
 *    la facture devrait être régénérée » · posé par les observers à
 *    chaque mutation impactante. Ne supprime pas la facture.
 *  - `deleted_at` + `superseded_by_id` matérialisent la régénération
 *    effective · posés uniquement par `RegenerateInvoiceAction` quand
 *    l'utilisateur a confirmé l'opération.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->softDeletes();
            $table->foreignId('superseded_by_id')
                ->nullable()
                ->after('is_divergent')
                ->constrained('invoices')
                ->nullOnDelete();
            $table->timestamp('obsolete_at')->nullable()->after('superseded_by_id');

            // Index sur superseded_by_id pour les requêtes inverse
            // (construction de la chaîne historique).
            $table->index('superseded_by_id', 'inv_superseded_by_idx');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropIndex('inv_superseded_by_idx');
            $table->dropConstrainedForeignId('superseded_by_id');
            $table->dropColumn('obsolete_at');
            $table->dropSoftDeletes();
        });
    }
};
