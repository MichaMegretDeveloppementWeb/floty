<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Table `assignments` — Entité pivot V0/V1 (granularité jour).
 *
 * Cf. 01-schema-metier.md § 6 + ADR-0005 (calcul jour par jour).
 *
 * **Une ligne = (véhicule, jour)**. Granularité jour, année civile.
 *
 * Statut post ADR-0014 : la table est conservée le temps du chantier de
 * bascule (04.F → 04.H) — le moteur fiscal V1 et le DemoSeeder s'appuient
 * encore dessus. La suppression définitive est l'objet de **04.H**.
 *
 * Contrainte critique (CDC § 2.4) : un véhicule ne peut être attribué qu'à
 * une seule entreprise sur un jour donné. Implémentée via triggers
 * `assignments_vehicle_date_active_*` qui filtrent les lignes
 * `deleted_at IS NULL` (libère le slot après soft-delete pour permettre la
 * re-création).
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
        });

        // Triggers UNIQUE filtré par soft-delete — MySQL uniquement.
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $this->createSoftDeleteTriggers();
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            $this->dropSoftDeleteTriggers();
        }

        Schema::dropIfExists('assignments');
    }

    private function createSoftDeleteTriggers(): void
    {
        $body = <<<'SQL'
            DECLARE clash_count INT;
            IF NEW.deleted_at IS NULL THEN
                SELECT COUNT(*) INTO clash_count
                FROM assignments
                WHERE vehicle_id = NEW.vehicle_id
                  AND `date` = NEW.`date`
                  AND deleted_at IS NULL
                  AND id <> COALESCE(NEW.id, 0);
                IF clash_count > 0 THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'assignments: vehicle already assigned for this date';
                END IF;
            END IF;
        SQL;

        DB::unprepared('CREATE TRIGGER assignments_vehicle_date_active_insert BEFORE INSERT ON assignments FOR EACH ROW BEGIN '.$body.' END');
        DB::unprepared('CREATE TRIGGER assignments_vehicle_date_active_update BEFORE UPDATE ON assignments FOR EACH ROW BEGIN '.$body.' END');
    }

    private function dropSoftDeleteTriggers(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS assignments_vehicle_date_active_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS assignments_vehicle_date_active_update');
    }
};
