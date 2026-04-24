<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Table `companies` — Entreprises utilisatrices de la flotte.
 *
 * Cf. 01-schema-metier.md § 4.
 *
 * Particularités :
 *   - UNIQUE (short_code) filtré par soft delete, émulé via colonne générée
 *     `short_code_active` (MySQL 8 ne supporte pas l'index partiel natif,
 *     cf. 01-schema-metier.md § 0.2).
 *   - UNIQUE (siren) filtré idem, appliqué uniquement si le SIREN est
 *     renseigné et l'entreprise non soft-deletée.
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
            $table->timestamp('deactivated_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'deleted_at']);

            // UNIQUE directs sur short_code et siren — fallback MVP sans
            // colonnes générées (Hostinger refuse les expressions
            // conditionnelles dans GENERATED ALWAYS AS). Conséquence : un
            // short_code / siren d'une entreprise soft-deletée ne peut
            // pas être réutilisé. Acceptable en démo ; à revoir en V1
            // via triggers BEFORE INSERT/UPDATE.
            $table->unique('short_code');
            $table->unique('siren');
        });

        // CHECK constraints — filet SQL défensif, ajouté via ALTER TABLE qui
        // n'est supporté qu'en MySQL 8 ; SQLite (tests unitaires) interdit
        // `ALTER TABLE ... ADD CONSTRAINT`. La validation applicative reste
        // la première ligne de défense dans les deux environnements.
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE companies
                ADD CONSTRAINT chk_companies_color
                CHECK (color IN ('indigo', 'emerald', 'amber', 'rose', 'violet', 'teal', 'orange', 'cyan'))
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
