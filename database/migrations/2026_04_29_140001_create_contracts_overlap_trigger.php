<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Triggers MySQL anti-overlap pour la table `contracts`.
 *
 * Cf. ADR-0014 D5 : un véhicule ne peut avoir deux contrats actifs
 * (non soft-deleted) qui se chevauchent dans le temps. Le trigger est
 * la **source de vérité** de cet invariant (la validation côté Action
 * est de la défense en profondeur pour produire un message FR explicite
 * avant que la requête atteigne la DB).
 *
 * **Pattern réutilisé** depuis
 * `2026_04_29_140002_replace_unique_with_soft_delete_triggers.php`
 * (équivalent pour assignments) : SQLSTATE '45000' + IF deleted_at IS
 * NULL + exclusion auto-référence (`id <> COALESCE(NEW.id, 0)`).
 *
 * **Logique d'overlap** : deux plages [a, b] et [c, d] se chevauchent
 * ssi `a <= d AND b >= c`. Pour un nouveau contrat
 * `[NEW.start_date, NEW.end_date]` sur le même véhicule, on cherche
 * les contrats actifs existants tels que
 * `start_date <= NEW.end_date AND end_date >= NEW.start_date`.
 *
 * **Driver SQLite** (tests legacy) : trigger non créé. La validation
 * applicative dans l'Action couvre ces cas.
 */
return new class extends Migration
{
    public function up(): void
    {
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
    }

    private function driverName(): string
    {
        return DB::connection()->getDriverName();
    }

    private function createTriggers(): void
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

        DB::unprepared(
            'CREATE TRIGGER contracts_no_overlap_insert BEFORE INSERT ON contracts FOR EACH ROW BEGIN '
            .$body
            .' END'
        );
        DB::unprepared(
            'CREATE TRIGGER contracts_no_overlap_update BEFORE UPDATE ON contracts FOR EACH ROW BEGIN '
            .$body
            .' END'
        );
    }

    private function dropExistingTriggers(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS contracts_no_overlap_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS contracts_no_overlap_update');
    }
};
