<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Triggers MySQL Floty — défenses SQL complémentaires aux validations
 * applicatives.
 *
 * Cf. 01-schema-metier.md § 0.4.
 *
 * Triggers créés :
 *
 * 1. **`vfc_no_overlap_insert`** et **`vfc_no_overlap_update`** sur
 *    `vehicle_fiscal_characteristics` :
 *
 *    Filet SQL contre le chevauchement de périodes d'effet pour un même
 *    véhicule — MySQL 8 n'a pas d'exclusion constraint native
 *    (cf. 01-schema-metier.md § 0.3).
 *
 *    La première ligne de défense reste le `VehicleFiscalCharacteristicsService`
 *    en applicatif (validation + verrou pessimiste) ; ce trigger rattrape
 *    toute écriture qui passerait en court-circuit du service.
 *
 * Les triggers sont créés via `DB::unprepared` car le Schema builder
 * Laravel ne sait pas les gérer. Le `DELIMITER` est évité en remplaçant
 * par un seul statement compact (pas de sous-statements avec `;`).
 */
return new class extends Migration
{
    public function up(): void
    {
        if ($this->driverName() !== 'mysql') {
            // Triggers réservés au driver MySQL (SQLSTATE/SIGNAL spécifiques).
            // Sur SQLite (tests) ou PostgreSQL (V2+), la validation
            // applicative `VehicleFiscalCharacteristicsService` reste la
            // ligne de défense — PostgreSQL basculera sur un EXCLUDE
            // constraint natif.
            return;
        }

        $this->dropExistingTriggers();

        $overlapCheckBody = <<<'SQL'
            DECLARE overlap_count INT;

            SELECT COUNT(*) INTO overlap_count
            FROM vehicle_fiscal_characteristics
            WHERE vehicle_id = NEW.vehicle_id
              AND id <> COALESCE(NEW.id, 0)
              AND NEW.effective_from <= COALESCE(effective_to, DATE('9999-12-31'))
              AND effective_from <= COALESCE(NEW.effective_to, DATE('9999-12-31'));

            IF overlap_count > 0 THEN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'vehicle_fiscal_characteristics: overlapping effective period for this vehicle';
            END IF;
        SQL;

        DB::unprepared(
            'CREATE TRIGGER vfc_no_overlap_insert'
            .' BEFORE INSERT ON vehicle_fiscal_characteristics'
            .' FOR EACH ROW BEGIN '.$overlapCheckBody.' END'
        );

        DB::unprepared(
            'CREATE TRIGGER vfc_no_overlap_update'
            .' BEFORE UPDATE ON vehicle_fiscal_characteristics'
            .' FOR EACH ROW BEGIN '.$overlapCheckBody.' END'
        );
    }

    public function down(): void
    {
        if ($this->driverName() !== 'mysql') {
            return;
        }

        $this->dropExistingTriggers();
    }

    private function driverName(): string
    {
        return DB::connection()->getDriverName();
    }

    private function dropExistingTriggers(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS vfc_no_overlap_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS vfc_no_overlap_update');
    }
};
