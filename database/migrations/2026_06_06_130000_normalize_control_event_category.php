<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data fix · the events migration backfilled the converted « contrôle
 * technique » events with the category « Contrôle réglementaire ». The control
 * domain now uses the simpler « Contrôle », so this normalizes the stored value
 * to « Contrôle » (it then no longer pollutes the autocomplete suggestions; a
 * user may still type it manually). Idempotent · no-op on a fresh DB.
 *
 * Collision guard: an event that already carries « Contrôle » would violate
 * UNIQUE(vehicle_event_id, category) on rename, so its « Contrôle
 * réglementaire » row is dropped instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(<<<'SQL'
            DELETE cr FROM `vehicle_event_categories` cr
            INNER JOIN `vehicle_event_categories` c2
                ON c2.`vehicle_event_id` = cr.`vehicle_event_id` AND c2.`category` = 'Contrôle'
            WHERE cr.`category` = 'Contrôle réglementaire'
        SQL);

        DB::statement(<<<'SQL'
            UPDATE `vehicle_event_categories`
            SET `category` = 'Contrôle'
            WHERE `category` = 'Contrôle réglementaire'
        SQL);
    }

    public function down(): void
    {
        // Data normalization · not reversible (the original distinction is lost).
    }
};
