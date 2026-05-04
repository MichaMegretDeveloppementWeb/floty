<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ADR-0016 rev. 1.1 - passage de l'enum `UnavailabilityType` de 5 à 9
 * valeurs et raffinement du CHECK `has_fiscal_impact`.
 *
 * Anciennes valeurs (5)         → Nouvelles (9)
 *   maintenance                  → maintenance              (inchangé, non réducteur)
 *   technical_inspection         → technical_inspection     (inchangé, non réducteur)
 *   accident                     → scindé en accident_repair (non réducteur, défaut)
 *                                              et accident_no_circulation (réducteur)
 *   pound                        → scindé en pound_public (réducteur, défaut)
 *                                              et pound_private (non réducteur)
 *   other                        → other                     (inchangé, non réducteur)
 *
 * Nouveaux cases ajoutés : `ci_suspension` (réducteur), `theft` (non
 * réducteur).
 *
 * Le CHECK `has_fiscal_impact` passe de `(type = 'pound')` à
 * `(type IN ('accident_no_circulation', 'pound_public', 'ci_suspension'))`
 * - les 3 cas réducteurs définis par ADR-0016 § 4.
 *
 * Données existantes : remappage `accident → accident_repair` et
 * `pound → pound_public` (choix par défaut conservatoire). Les seules
 * données concernées sont les fixtures du DemoSeeder (régénéré à chaque
 * `migrate:fresh --seed`).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        // Étape 1 : élargir provisoirement l'enum à l'union ancien+nouveau pour
        // permettre l'UPDATE de remappage sans violer le CHECK.
        DB::statement('ALTER TABLE unavailabilities DROP CONSTRAINT chk_unavailabilities_type_enum');
        DB::statement('ALTER TABLE unavailabilities DROP CONSTRAINT chk_unavailabilities_fiscal_impact_consistent');
        DB::statement(<<<'SQL'
            ALTER TABLE unavailabilities
                MODIFY COLUMN type ENUM(
                    'maintenance',
                    'technical_inspection',
                    'accident',
                    'pound',
                    'accident_repair',
                    'accident_no_circulation',
                    'pound_public',
                    'pound_private',
                    'ci_suspension',
                    'theft',
                    'other'
                ) NOT NULL
        SQL);

        // Étape 2 : remappage des anciennes valeurs vers leur défaut conservatoire.
        DB::statement("UPDATE unavailabilities SET type = 'accident_repair' WHERE type = 'accident'");
        DB::statement("UPDATE unavailabilities SET type = 'pound_public' WHERE type = 'pound'");

        // Étape 3 : ré-établir l'enum strict à 9 valeurs et les CHECKs.
        DB::statement(<<<'SQL'
            ALTER TABLE unavailabilities
                MODIFY COLUMN type ENUM(
                    'maintenance',
                    'technical_inspection',
                    'accident_repair',
                    'accident_no_circulation',
                    'pound_public',
                    'pound_private',
                    'ci_suspension',
                    'theft',
                    'other'
                ) NOT NULL
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE unavailabilities
                ADD CONSTRAINT chk_unavailabilities_type_enum
                CHECK (type IN (
                    'maintenance',
                    'technical_inspection',
                    'accident_repair',
                    'accident_no_circulation',
                    'pound_public',
                    'pound_private',
                    'ci_suspension',
                    'theft',
                    'other'
                ))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE unavailabilities
                ADD CONSTRAINT chk_unavailabilities_fiscal_impact_consistent
                CHECK (has_fiscal_impact = (type IN (
                    'accident_no_circulation',
                    'pound_public',
                    'ci_suspension'
                )))
        SQL);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE unavailabilities DROP CONSTRAINT chk_unavailabilities_type_enum');
        DB::statement('ALTER TABLE unavailabilities DROP CONSTRAINT chk_unavailabilities_fiscal_impact_consistent');

        DB::statement(<<<'SQL'
            ALTER TABLE unavailabilities
                MODIFY COLUMN type ENUM(
                    'maintenance',
                    'technical_inspection',
                    'accident',
                    'pound',
                    'accident_repair',
                    'accident_no_circulation',
                    'pound_public',
                    'pound_private',
                    'ci_suspension',
                    'theft',
                    'other'
                ) NOT NULL
        SQL);

        DB::statement("UPDATE unavailabilities SET type = 'accident' WHERE type IN ('accident_repair', 'accident_no_circulation')");
        DB::statement("UPDATE unavailabilities SET type = 'pound' WHERE type IN ('pound_public', 'pound_private')");
        DB::statement("UPDATE unavailabilities SET type = 'other' WHERE type IN ('ci_suspension', 'theft')");

        DB::statement(<<<'SQL'
            ALTER TABLE unavailabilities
                MODIFY COLUMN type ENUM(
                    'maintenance',
                    'technical_inspection',
                    'accident',
                    'pound',
                    'other'
                ) NOT NULL
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE unavailabilities
                ADD CONSTRAINT chk_unavailabilities_type_enum
                CHECK (type IN ('maintenance', 'technical_inspection', 'accident', 'pound', 'other'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE unavailabilities
                ADD CONSTRAINT chk_unavailabilities_fiscal_impact_consistent
                CHECK (has_fiscal_impact = (type = 'pound'))
        SQL);
    }
};
