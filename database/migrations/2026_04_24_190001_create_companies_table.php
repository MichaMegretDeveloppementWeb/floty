<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Table `companies` - Entreprises utilisatrices de la flotte.
 *
 * Cf. 01-schema-metier.md § 4.
 *
 * Particularités :
 *   - UNIQUE (short_code) filtré par soft delete via triggers
 *     `companies_short_code_active_*` (MySQL refuse les expressions
 *     conditionnelles dans GENERATED ALWAYS AS, cf. 01-schema-metier.md § 0.2).
 *   - UNIQUE (siren) filtré idem, appliqué uniquement si le SIREN est
 *     renseigné et l'entreprise non soft-deletée.
 *   - Drapeaux d'exonération pour le moteur fiscal (R-2024-018, R-2024-019) :
 *     `is_oig`, `is_individual_business`. La colonne `exempted_activity`
 *     a été retirée par la migration 2026_04_30_175040 (cleanup R-2024-022).
 *   - Deux drapeaux orthogonaux :
 *       * `is_active` = désactivation métier (plus d'attributions futures
 *         mais historique conservé, visible en lecture),
 *       * `deleted_at` = soft delete fonctionnel (invisible dans les listes
 *         standard mais lignes conservées pour l'intégrité des snapshots).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table): void {
            $table->id();
            $table->string('legal_name');

            $table->char('siren', 9)->nullable();
            $table->char('siret', 14)->nullable();

            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('city', 100)->nullable();
            $table->char('country', 2)->default('FR');

            $table->string('contact_name', 150)->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 30)->nullable();

            $table->string('short_code', 5);
            $table->string('color', 10);

            $table->boolean('is_active')->default(true);
            $table->boolean('is_oig')->default(false);
            $table->boolean('is_individual_business')->default(false);
            $table->string('exempted_activity', 32)->default('none');
            $table->timestamp('deactivated_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'deleted_at']);
            $table->index('short_code');
            $table->index('siren');
        });

        // CHECK constraints + triggers - MySQL uniquement (SQLite tests
        // legacy n'a pas SIGNAL/SQLSTATE, la validation applicative reste
        // la première ligne de défense).
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE companies
                ADD CONSTRAINT chk_companies_color
                CHECK (color IN ('indigo', 'emerald', 'amber', 'rose', 'violet', 'teal', 'orange', 'cyan'))
        SQL);

        // Triggers UNIQUE filtré par soft-delete (cf. note ci-dessus -
        // MySQL refuse les expressions conditionnelles dans GENERATED
        // ALWAYS AS, donc on émule via SIGNAL).
        $this->createSoftDeleteTriggers();
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            $this->dropSoftDeleteTriggers();
        }

        Schema::dropIfExists('companies');
    }

    private function createSoftDeleteTriggers(): void
    {
        // companies.short_code (NOT NULL)
        $shortCodeBody = <<<'SQL'
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
        DB::unprepared('CREATE TRIGGER companies_short_code_active_insert BEFORE INSERT ON companies FOR EACH ROW BEGIN '.$shortCodeBody.' END');
        DB::unprepared('CREATE TRIGGER companies_short_code_active_update BEFORE UPDATE ON companies FOR EACH ROW BEGIN '.$shortCodeBody.' END');

        // companies.siren (NULL toléré)
        $sirenBody = <<<'SQL'
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
        DB::unprepared('CREATE TRIGGER companies_siren_active_insert BEFORE INSERT ON companies FOR EACH ROW BEGIN '.$sirenBody.' END');
        DB::unprepared('CREATE TRIGGER companies_siren_active_update BEFORE UPDATE ON companies FOR EACH ROW BEGIN '.$sirenBody.' END');
    }

    private function dropSoftDeleteTriggers(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS companies_short_code_active_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS companies_short_code_active_update');
        DB::unprepared('DROP TRIGGER IF EXISTS companies_siren_active_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS companies_siren_active_update');
    }
};
