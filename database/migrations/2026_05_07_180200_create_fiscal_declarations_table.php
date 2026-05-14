<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Table `fiscal_declarations` · entité racine du workflow déclaratif
 * (Phase 11 D1, ADR-0015 § 5.1 rev. 1.1).
 *
 * Une déclaration est rattachée à un couple `(company_id, fiscal_year)`,
 * mais plusieurs déclarations historiques peuvent coexister pour ce
 * couple grâce à la chaîne d'obsolescence (`is_obsolete` +
 * `superseded_by_id`). À tout instant, **au plus une** déclaration est
 * active (`is_obsolete = false`) pour un couple donné ; cette unicité
 * fonctionnelle est garantie par le repository, pas par contrainte SQL.
 *
 * Statut applicatif :
 *   - `draft` : en cours de revue, peut être modifiée.
 *   - `deferred` : indicateur visuel volontaire (utilisateur veut
 *     consulter son EC), n'autorise pas la génération.
 *   - `generated` : PDF annexe produit, snapshot immuable. Peut être
 *     marquée obsolète mais ne revient jamais en `draft`.
 *
 * Obsolescence (rev. 1.1) :
 *   - `is_obsolete` : flag posé par les Observers quand une action
 *     fiscalement impactante est détectée sur un contrat / VFC /
 *     indispo de l'année.
 *   - `obsolete_at` : horodatage du premier marquage.
 *   - `obsolete_reasons` : JSON typé (cf. ADR-0015 § D9), tableau
 *     d'événements empilés à chaque action invalidante.
 *   - `superseded_by_id` : référence vers la déclaration qui remplace
 *     celle-ci après régénération (FK self).
 *
 * Soft-delete activé : une déclaration `generated` ne peut être
 * supprimée définitivement (cf. ADR-0015 § D8 point 7).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_declarations', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->restrictOnDelete();

            $table->unsignedSmallInteger('fiscal_year');
            $table->string('reference', 32)->nullable();

            $table->string('status', 16)->default('draft');

            $table->timestamp('generated_at')->nullable();
            $table->string('generated_pdf_path', 255)->nullable();
            $table->string('generated_pdf_hash', 64)->nullable();
            $table->json('generated_snapshot_payload')->nullable();

            $table->boolean('is_obsolete')->default(false);
            $table->timestamp('obsolete_at')->nullable();
            $table->json('obsolete_reasons')->nullable();

            $table->foreignId('superseded_by_id')
                ->nullable()
                ->constrained('fiscal_declarations')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'fiscal_year', 'is_obsolete'], 'decl_company_year_active_idx');
            $table->index('status', 'decl_status_idx');
            $table->index('superseded_by_id', 'decl_superseded_by_idx');
            $table->index(['company_id', 'fiscal_year', 'reference'], 'decl_company_year_reference_idx');
        });

        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE fiscal_declarations ADD CONSTRAINT chk_decl_year_range CHECK (fiscal_year BETWEEN 2020 AND 2099)');
        DB::statement("ALTER TABLE fiscal_declarations ADD CONSTRAINT chk_decl_status_enum CHECK (status IN ('draft', 'deferred', 'generated'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_declarations');
    }
};
