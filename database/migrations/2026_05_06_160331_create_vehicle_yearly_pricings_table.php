<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Table `vehicle_yearly_pricings` - Tarifs jour/semaine/mois d'un véhicule
 * pour une année fiscale donnée. Pierre angulaire du module Facturation
 * (Phase 14 V1.2).
 *
 * Cf. spec Phase 14 (`project-management/plan-implementation/tasks/phase-14-billing/`).
 *
 * Pour un véhicule donné :
 *   - Une seule ligne par année (contrainte UNIQUE(vehicle_id, year))
 *     → permet l'upsert idempotent via `updateOrCreate`.
 *   - Tarifs en cents (convention monétaire projet, cf. `declarations.total_*_tax_cents`)
 *     pour éviter les imprécisions float.
 *   - Pas d'historisation intra-année : si un tarif change en cours d'année
 *     l'utilisateur écrase la ligne existante (responsabilité utilisateur de
 *     refacturer s'il a déjà généré des factures avec l'ancien tarif).
 *     L'historisation tarifaire intra-année est reportée V2 (cf. mémoire
 *     `roadmap_v12_facturation`).
 *
 * Le `BillingCalculator` (chantier 14.C) lèvera `MissingPricingException`
 * si on tente de calculer une facture pour un (vehicle, year) absent de
 * cette table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_yearly_pricings', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('vehicle_id')
                ->constrained('vehicles')
                ->restrictOnDelete();

            $table->unsignedSmallInteger('year');

            // Tarifs en cents (100 = 1,00 €). unsignedInteger autorise
            // jusqu'à ~42 949 €/jour, largement suffisant.
            $table->unsignedInteger('daily_rate_cents');
            $table->unsignedInteger('weekly_rate_cents');
            $table->unsignedInteger('monthly_rate_cents');

            $table->timestamps();

            // UNIQUE garantit l'idempotence de l'upsert (1 tarif par
            // couple véhicule × année) ET sert d'index pour la lookup
            // `findForVehicleAndYear(vehicleId, year)` · pas besoin
            // d'index dédié supplémentaire (Lot 6 D4 · F-31-009).
            $table->unique(['vehicle_id', 'year']);
        });

        // CHECK contraintes — MySQL uniquement (SQLite ne supporte pas
        // ALTER TABLE ADD CONSTRAINT). Cohérence avec la migration VFC.
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        // Année fiscale dans une plage raisonnable (2020-2099) pour
        // éviter les saisies aberrantes (typo).
        DB::statement(<<<'SQL'
            ALTER TABLE vehicle_yearly_pricings
                ADD CONSTRAINT chk_vyp_year_range
                CHECK (year BETWEEN 2020 AND 2099)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_yearly_pricings');
    }
};
