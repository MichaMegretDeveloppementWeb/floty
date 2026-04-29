<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Remplace la valeur générique `effective_change` par 3 motifs plus
 * fins exposés à l'utilisateur dans le sélecteur de la page Edit
 * véhicule (mode « Nouvelle version ») :
 *
 *   - `recharacterization` (reclassement fiscal)
 *   - `regulation_change`  (changement réglementaire)
 *   - `other_change`       (autre — `change_note` requis côté UI)
 *
 * Aucune donnée existante n'utilise `effective_change` (la seule
 * valeur posée par le seeder + factory + `CreateVehicleAction` est
 * `initial_creation`), donc on peut remplacer le CHECK directement.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE vehicle_fiscal_characteristics DROP CHECK chk_vfc_change_reason_enum');

        DB::statement(<<<'SQL'
            ALTER TABLE vehicle_fiscal_characteristics
                ADD CONSTRAINT chk_vfc_change_reason_enum
                CHECK (change_reason IN (
                    'initial_creation',
                    'recharacterization',
                    'regulation_change',
                    'other_change',
                    'input_correction'
                ))
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE vehicle_fiscal_characteristics DROP CHECK chk_vfc_change_reason_enum');

        DB::statement(<<<'SQL'
            ALTER TABLE vehicle_fiscal_characteristics
                ADD CONSTRAINT chk_vfc_change_reason_enum
                CHECK (change_reason IN ('initial_creation', 'effective_change', 'input_correction'))
        SQL);
    }
};
