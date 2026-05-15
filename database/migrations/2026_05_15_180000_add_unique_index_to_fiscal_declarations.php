<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lot 5 D3 (F-19-006) · garantit par construction l'invariant
 * « au plus 1 déclaration active par couple `(company_id, fiscal_year)` »
 * via un index unique partial.
 *
 * Avant cette migration, l'invariant était assuré uniquement par
 * `CreateDraftDeclarationAction` qui check `findActiveForCompanyYear`
 * puis insert · TOCTOU possible en concurrence (2 utilisateurs créent
 * simultanément) et aucun filet en cas de bug applicatif futur. Le
 * code de production V1 mérite une garantie BDD, défense en profondeur.
 *
 * **Workaround MySQL 8** · MySQL ne supporte pas `CREATE UNIQUE INDEX
 * ... WHERE` (index partial PostgreSQL). On utilise une **colonne
 * virtuelle générée** qui vaut `CONCAT(company_id, '-', fiscal_year)`
 * ssi la déclaration est active (non soft-deleted, non obsolète),
 * `NULL` sinon. Un index UNIQUE sur cette colonne tolère les NULLs
 * (comportement SQL standard) · plusieurs lignes obsolètes ou
 * supprimées peuvent coexister, mais une seule active.
 *
 * **Pré-requis** · vérifié au préalable qu'aucun doublon actif
 * n'existe en BDD (Lot 5 D3 cartographie · 0 doublon constaté).
 * Si la migration échoue avec un Duplicate entry, c'est qu'un
 * doublon a été créé entre la cartographie et la migration · à
 * traiter manuellement (résoudre les doublons puis relancer).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('
            ALTER TABLE fiscal_declarations
            ADD COLUMN active_uniqueness_key VARCHAR(64)
            GENERATED ALWAYS AS (
                CASE
                    WHEN deleted_at IS NULL AND is_obsolete = 0
                    THEN CONCAT(company_id, "-", fiscal_year)
                    ELSE NULL
                END
            ) VIRTUAL
        ');

        DB::statement('
            ALTER TABLE fiscal_declarations
            ADD CONSTRAINT decl_active_uniqueness UNIQUE (active_uniqueness_key)
        ');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE fiscal_declarations DROP INDEX decl_active_uniqueness');
        DB::statement('ALTER TABLE fiscal_declarations DROP COLUMN active_uniqueness_key');
    }
};
