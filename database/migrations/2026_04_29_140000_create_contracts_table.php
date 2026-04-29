<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Table `contracts` — Entité pivot du domaine fiscal après refonte
 * 2026-04-29 (cf. ADR-0014 « Modèle Contract et règle LCD par contrat
 * individuel »).
 *
 * **Une ligne = un contrat de location** (vehicle × company × plage
 * temporelle inclusive `[start_date, end_date]`). Remplace à terme la
 * table `assignments` (1 ligne par jour). La migration de bascule est
 * portée par les chantiers 04.F → 04.H.
 *
 * **Invariant critique** (ADR-0014 D5) : un véhicule ne peut avoir
 * deux contrats actifs (non soft-deleted) qui se chevauchent dans le
 * temps. Implémenté par les triggers MySQL de la migration suivante
 * `2026_04_29_140001_create_contracts_overlap_trigger.php`.
 *
 * **Invariants colonnes** :
 *   - `end_date` NOT NULL (cohérent ADR-0014 D4 : tout contrat a une
 *     fin connue à la signature)
 *   - `CHECK end_date >= start_date` (refus DB d'un contrat « inversé »)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('vehicle_id')
                ->constrained('vehicles')
                ->cascadeOnDelete();
            $table->foreignId('company_id')
                ->constrained('companies')
                ->restrictOnDelete();
            $table->foreignId('driver_id')
                ->nullable()
                ->constrained('drivers')
                ->nullOnDelete();

            $table->date('start_date');
            $table->date('end_date');

            $table->string('contract_reference', 64)->nullable();

            $table->enum('contract_type', ['lcd', 'lld', 'mise_a_disposition_assimilee']);

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes pour les requêtes de chevauchement et de lecture
            // par véhicule × année (ADR-0014 § 3 schéma).
            $table->index(['vehicle_id', 'start_date', 'end_date']);
            $table->index(['company_id', 'start_date']);
            $table->index(['start_date', 'end_date']);
        });

        // CHECK constraint `end_date >= start_date`. Schema Builder
        // Laravel ne supporte pas les CHECK natifs ; on passe par
        // ALTER TABLE brut. MySQL 8+ supporte les CHECK depuis 8.0.16.
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE contracts ADD CONSTRAINT chk_contracts_end_after_start '
                .'CHECK (end_date >= start_date)'
            );
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE contracts DROP CONSTRAINT IF EXISTS chk_contracts_end_after_start');
        }

        Schema::dropIfExists('contracts');
    }
};
