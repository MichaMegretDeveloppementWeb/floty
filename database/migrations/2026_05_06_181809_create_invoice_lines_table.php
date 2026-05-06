<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `invoice_lines` — Lignes individuelles d'une facture mensuelle
 * (Phase 14.E V1.2).
 *
 * Une ligne = un véhicule sur le mois facturé. Snapshot complet :
 *   - identité (`vehicle_label_snapshot` figé pour archivage si le
 *     véhicule est ensuite supprimé/modifié)
 *   - jours utilisés (`days_used`)
 *   - décomposition tarifaire optimale (`months_billed` / `weeks_billed` /
 *     `days_billed`) issue de `OptimalRateBreakdown`
 *   - tarifs unitaires snapshot (`daily_rate_cents` / `weekly_rate_cents` /
 *     `monthly_rate_cents`) — figés à l'émission, ne suivent pas les
 *     mises à jour ultérieures du `vehicle_yearly_pricings`
 *   - total ligne (`total_ht_cents`)
 *
 * **`vehicle_id` est FK contrainte `restrictOnDelete`** : on bloque
 * la suppression d'un véhicule qui figure dans une facture émise.
 * L'utilisateur doit utiliser le retrait de flotte (`exit_date`) plutôt
 * qu'une suppression hard (cf. ADR-0018).
 *
 * Les colonnes de snapshot évitent qu'une mutation amont (rename d'une
 * plaque, modif de tarif) corrompe les factures émises.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_lines', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('invoice_id')
                ->constrained('invoices')
                ->cascadeOnDelete();

            $table->foreignId('vehicle_id')
                ->constrained('vehicles')
                ->restrictOnDelete();

            // Snapshot identité véhicule (figé à l'émission).
            $table->string('vehicle_label_snapshot', 128);

            // Brut métier.
            $table->unsignedSmallInteger('days_used');

            // Décomposition tarifaire optimale.
            $table->unsignedTinyInteger('months_billed');
            $table->unsignedTinyInteger('weeks_billed');
            $table->unsignedTinyInteger('days_billed');

            // Snapshot tarifs (cents).
            $table->unsignedInteger('daily_rate_cents');
            $table->unsignedInteger('weekly_rate_cents');
            $table->unsignedInteger('monthly_rate_cents');

            // Total ligne = months × monthly + weeks × weekly + days × daily.
            $table->unsignedInteger('total_ht_cents');

            $table->timestamps();

            // Lookups standard : récupérer toutes les lignes d'une facture.
            $table->index('invoice_id', 'invl_invoice_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
    }
};
