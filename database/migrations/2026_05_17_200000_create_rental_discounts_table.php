<?php

declare(strict_types=1);

use App\Services\RentalDiscount\RentalDiscountConflictService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Table `rental_discounts` · réductions commerciales appliquées sur le
 * montant des loyers facturés à une entreprise utilisatrice (V1.2 post
 * Phase 14).
 *
 * Une ligne = un engagement contractuel `(entreprise × période ×
 * pourcentage)`. Liste de véhicules concernés portée par la table pivot
 * `rental_discount_vehicles` (vide = applique à tous les véhicules de la
 * company sur la période · décision applicative, pas SQL).
 *
 * **Pourcentage stocké en basis points** (1 bp = 0,01 %) ·
 *   - cohérent avec la doctrine projet « cents partout » (entiers exclusivement)
 *   - évite les imprécisions float même rares
 *   - permet 2 décimales métier (ex. 10,50 % = 1050 bp)
 *
 * **Soft deletes** ·
 *   - permet la restauration accidentelle après suppression
 *   - préserve la référence FK depuis `invoice_lines.applied_discount_id`
 *     pour les factures émises (audit immuable)
 *
 * **Non-chevauchement** validé applicativement (cf.
 * {@see RentalDiscountConflictService}) car
 * la logique implique l'intersection véhicules (pivot) qu'un trigger
 * MySQL ne peut pas exprimer simplement. Test feature dédié garantit
 * l'invariant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_discounts', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->restrictOnDelete();

            // Période d'application (inclusive).
            $table->date('start_date');
            $table->date('end_date');

            // Pourcentage en basis points (1..10000 = 0,01 %..100,00 %).
            // 0 bp interdit (réduction vide = pas de réduction, à supprimer).
            $table->unsignedSmallInteger('discount_basis_points');

            // Libellé marketing optionnel ("Pack fidélité 2026").
            $table->string('label', 120)->nullable();

            // Notes internes (contexte commercial, lien commande, etc.).
            $table->text('notes')->nullable();

            // Traçabilité auteur création (peut être null si user supprimé).
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Index principal · résolution rapide « réductions actives
            // pour une (entreprise × année) » consommée par DiscountResolver.
            $table->index(['company_id', 'start_date', 'end_date'], 'rd_company_period_idx');
        });

        // CHECK contraintes · MySQL uniquement (SQLite skippe les
        // CHECK post-création, cohérent avec les autres migrations).
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE rental_discounts
                ADD CONSTRAINT chk_rd_end_after_start
                CHECK (end_date >= start_date)
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE rental_discounts
                ADD CONSTRAINT chk_rd_bp_range
                CHECK (discount_basis_points BETWEEN 1 AND 10000)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_discounts');
    }
};
