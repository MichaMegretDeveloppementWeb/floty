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
 * **Invariants critiques** :
 *   - `end_date` NOT NULL (cohérent ADR-0014 D4 : tout contrat a une
 *     fin connue à la signature)
 *   - `CHECK end_date >= start_date` (refus DB d'un contrat « inversé »)
 *   - Triggers MySQL `contracts_no_overlap_*` : un véhicule ne peut avoir
 *     deux contrats actifs (non soft-deleted) qui se chevauchent. Les
 *     triggers filtrent `deleted_at IS NULL` et excluent l'auto-référence
 *     via `id <> COALESCE(NEW.id, 0)`. Logique d'overlap : deux plages
 *     `[a, b]` et `[c, d]` se chevauchent ssi `a <= d AND b >= c`.
 *
 * **Driver SQLite** (tests legacy) : triggers et CHECK non créés. La
 * validation applicative dans les Actions Contract couvre ces cas.
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

        // CHECK constraint + triggers — MySQL uniquement (Schema Builder
        // Laravel ne supporte pas les CHECK natifs ; SQLite ne supporte
        // ni `ALTER TABLE ADD CONSTRAINT` ni SIGNAL/SQLSTATE).
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            'ALTER TABLE contracts ADD CONSTRAINT chk_contracts_end_after_start '
            .'CHECK (end_date >= start_date)'
        );

        $this->createOverlapTriggers();
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            $this->dropOverlapTriggers();
            DB::statement('ALTER TABLE contracts DROP CONSTRAINT IF EXISTS chk_contracts_end_after_start');
        }

        Schema::dropIfExists('contracts');
    }

    private function createOverlapTriggers(): void
    {
        $body = <<<'SQL'
            DECLARE clash_count INT;
            IF NEW.deleted_at IS NULL THEN
                SELECT COUNT(*) INTO clash_count
                FROM contracts
                WHERE vehicle_id = NEW.vehicle_id
                  AND deleted_at IS NULL
                  AND id <> COALESCE(NEW.id, 0)
                  AND start_date <= NEW.end_date
                  AND end_date   >= NEW.start_date;
                IF clash_count > 0 THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'contracts: overlapping period for this vehicle';
                END IF;
            END IF;
        SQL;

        DB::unprepared('CREATE TRIGGER contracts_no_overlap_insert BEFORE INSERT ON contracts FOR EACH ROW BEGIN '.$body.' END');
        DB::unprepared('CREATE TRIGGER contracts_no_overlap_update BEFORE UPDATE ON contracts FOR EACH ROW BEGIN '.$body.' END');
    }

    private function dropOverlapTriggers(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS contracts_no_overlap_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS contracts_no_overlap_update');
    }
};
