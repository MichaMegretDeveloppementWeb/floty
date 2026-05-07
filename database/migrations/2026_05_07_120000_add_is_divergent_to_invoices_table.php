<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute la colonne `is_divergent` sur `invoices` (T6 / Phase 14.R).
 *
 * Flag matérialisé : passe à `true` via observers (Contract,
 * VehicleYearlyPricing, Vehicle.exit_date) dès qu'une mutation
 * pertinente survient sur le périmètre couvert par la facture.
 * Repassé à `false` à la régénération.
 *
 * Permet au filtre `divergentOnly` de l'Index Invoices d'être un
 * simple `WHERE is_divergent = 1` SQL natif, et au badge "À régénérer"
 * d'être lu en column-fetch — supprimant le N+1
 * `BillingCalculator::calculate` qui dégradait l'Index.
 *
 * Doctrine immuabilité préservée (ADR-0008) : `is_divergent` est une
 * métadonnée d'observabilité, distincte du snapshot figé
 * (`total_ht_cents`, `pdf_path`, `pdf_hash`, `invoice_number`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->boolean('is_divergent')
                ->default(false)
                ->after('total_ht_cents');

            // Index single-column : la majorité des factures auront
            // `is_divergent = false` ; le filtrage `divergentOnly` lit
            // un sous-ensemble minoritaire, l'index est très efficace.
            $table->index('is_divergent', 'inv_is_divergent_idx');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropIndex('inv_is_divergent_idx');
            $table->dropColumn('is_divergent');
        });
    }
};
