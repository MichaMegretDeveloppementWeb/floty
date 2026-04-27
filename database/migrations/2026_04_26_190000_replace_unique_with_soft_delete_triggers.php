<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rétablit la sémantique « UNIQUE filtré par soft-delete » pour les
 * colonnes qui en avaient besoin avant le fix Hostinger (qui avait
 * remplacé les colonnes générées par des UNIQUE simples).
 *
 * **Pourquoi** : avec un UNIQUE simple, une ligne soft-deletée
 * conserve son slot et empêche la réutilisation de la valeur — ex.
 * impossible de re-créer un véhicule avec une plaque déjà attribuée à
 * un véhicule sorti de la flotte.
 *
 * **Pourquoi pas un index sur colonne générée** : MariaDB / MySQL en
 * mode strict (Hostinger) refusent toute expression conditionnelle
 * dans `GENERATED ALWAYS AS` (`CASE WHEN`, `IF()`, …).
 *
 * **Solution retenue** : triggers `BEFORE INSERT` + `BEFORE UPDATE`
 * qui rejettent (`SQLSTATE '45000'`) toute écriture qui créerait une
 * ligne **active** (`deleted_at IS NULL`) entrant en collision avec
 * une autre ligne active. Pattern déjà utilisé pour
 * `vfc_no_overlap_*` dans la migration triggers fiscale.
 *
 * **Tables couvertes** :
 *   - `companies.short_code`            (NOT NULL)
 *   - `companies.siren`                 (NULL toléré → ignorer NULL)
 *   - `vehicles.license_plate`          (NOT NULL)
 *   - `vehicles.vin`                    (NULL toléré → ignorer NULL)
 *   - `assignments.(vehicle_id, date)`  (couple)
 *
 * **Index conservés** : index non-unique sur la colonne reste utile
 * pour les recherches (`WHERE license_plate = ?`).
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Drop des UNIQUE simples laissés par le fix Hostinger
        Schema::table('companies', function (Blueprint $t): void {
            $t->dropUnique(['short_code']);
            $t->dropUnique(['siren']);
            $t->index('short_code');
            $t->index('siren');
        });

        Schema::table('vehicles', function (Blueprint $t): void {
            $t->dropUnique(['license_plate']);
            $t->dropUnique(['vin']);
            $t->index('license_plate');
            $t->index('vin');
        });

        Schema::table('assignments', function (Blueprint $t): void {
            $t->dropUnique(['vehicle_id', 'date']);
            // L'index (vehicle_id, date) existe déjà depuis la migration initiale.
        });

        // 2. Triggers MySQL — les autres drivers (SQLite tests legacy)
        // s'appuient sur la validation applicative.
        if ($this->driverName() !== 'mysql') {
            return;
        }

        $this->dropExistingTriggers();
        $this->createTriggers();
    }

    public function down(): void
    {
        if ($this->driverName() === 'mysql') {
            $this->dropExistingTriggers();
        }

        // Restauration des UNIQUE simples (état après fix Hostinger).
        Schema::table('companies', function (Blueprint $t): void {
            $t->dropIndex(['short_code']);
            $t->dropIndex(['siren']);
            $t->unique('short_code');
            $t->unique('siren');
        });

        Schema::table('vehicles', function (Blueprint $t): void {
            $t->dropIndex(['license_plate']);
            $t->dropIndex(['vin']);
            $t->unique('license_plate');
            $t->unique('vin');
        });

        Schema::table('assignments', function (Blueprint $t): void {
            $t->unique(['vehicle_id', 'date']);
        });
    }

    private function driverName(): string
    {
        return DB::connection()->getDriverName();
    }

    private function createTriggers(): void
    {
        // Pattern réutilisable : check qu'aucune ligne ACTIVE n'a déjà
        // la valeur ; ignorer NEW.id pour les UPDATE ; ignorer NULL pour
        // les colonnes nullable.

        // ── companies.short_code (NOT NULL) ─────────────────────────
        $companyShortCode = <<<'SQL'
            DECLARE clash_count INT;
            IF NEW.deleted_at IS NULL THEN
                SELECT COUNT(*) INTO clash_count
                FROM companies
                WHERE short_code = NEW.short_code
                  AND deleted_at IS NULL
                  AND id <> COALESCE(NEW.id, 0);
                IF clash_count > 0 THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'companies: short_code already used by an active company';
                END IF;
            END IF;
        SQL;
        $this->addPair('companies', 'short_code_active', $companyShortCode);

        // ── companies.siren (NULL toléré) ───────────────────────────
        $companySiren = <<<'SQL'
            DECLARE clash_count INT;
            IF NEW.deleted_at IS NULL AND NEW.siren IS NOT NULL THEN
                SELECT COUNT(*) INTO clash_count
                FROM companies
                WHERE siren = NEW.siren
                  AND deleted_at IS NULL
                  AND id <> COALESCE(NEW.id, 0);
                IF clash_count > 0 THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'companies: siren already used by an active company';
                END IF;
            END IF;
        SQL;
        $this->addPair('companies', 'siren_active', $companySiren);

        // ── vehicles.license_plate (NOT NULL) ───────────────────────
        $vehicleLicensePlate = <<<'SQL'
            DECLARE clash_count INT;
            IF NEW.deleted_at IS NULL THEN
                SELECT COUNT(*) INTO clash_count
                FROM vehicles
                WHERE license_plate = NEW.license_plate
                  AND deleted_at IS NULL
                  AND id <> COALESCE(NEW.id, 0);
                IF clash_count > 0 THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'vehicles: license_plate already used by an active vehicle';
                END IF;
            END IF;
        SQL;
        $this->addPair('vehicles', 'license_plate_active', $vehicleLicensePlate);

        // ── vehicles.vin (NULL toléré) ──────────────────────────────
        $vehicleVin = <<<'SQL'
            DECLARE clash_count INT;
            IF NEW.deleted_at IS NULL AND NEW.vin IS NOT NULL THEN
                SELECT COUNT(*) INTO clash_count
                FROM vehicles
                WHERE vin = NEW.vin
                  AND deleted_at IS NULL
                  AND id <> COALESCE(NEW.id, 0);
                IF clash_count > 0 THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'vehicles: vin already used by an active vehicle';
                END IF;
            END IF;
        SQL;
        $this->addPair('vehicles', 'vin_active', $vehicleVin);

        // ── assignments.(vehicle_id, date) ──────────────────────────
        $assignmentSlot = <<<'SQL'
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
        $this->addPair('assignments', 'vehicle_date_active', $assignmentSlot);
    }

    private function addPair(string $table, string $name, string $body): void
    {
        DB::unprepared(sprintf(
            'CREATE TRIGGER %s_%s_insert BEFORE INSERT ON %s FOR EACH ROW BEGIN %s END',
            $table,
            $name,
            $table,
            $body,
        ));
        DB::unprepared(sprintf(
            'CREATE TRIGGER %s_%s_update BEFORE UPDATE ON %s FOR EACH ROW BEGIN %s END',
            $table,
            $name,
            $table,
            $body,
        ));
    }

    private function dropExistingTriggers(): void
    {
        $names = [
            'companies_short_code_active',
            'companies_siren_active',
            'vehicles_license_plate_active',
            'vehicles_vin_active',
            'assignments_vehicle_date_active',
        ];
        foreach ($names as $name) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$name}_insert");
            DB::unprepared("DROP TRIGGER IF EXISTS {$name}_update");
        }
    }
};
