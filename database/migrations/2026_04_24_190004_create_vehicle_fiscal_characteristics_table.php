<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Table `vehicle_fiscal_characteristics` - Historisation des caractéristiques
 * fiscalement déterminantes.
 *
 * Cf. 01-schema-metier.md § 3.
 *
 * Pour un véhicule donné :
 *   - Chaque modification effective (conversion E85, 2ᵉ rang ajouté…) crée
 *     une **nouvelle ligne** avec `effective_from` = jour du changement.
 *     La ligne précédente voit son `effective_to` fermé à la veille.
 *   - Les périodes ne se chevauchent jamais - garanti par :
 *       1. Validation service (première ligne de défense).
 *       2. Triggers MySQL `BEFORE INSERT/UPDATE` `vfc_no_overlap_*`.
 *       3. Verrou pessimiste côté service lors de la lecture de la version
 *          courante.
 *   - `effective_to IS NULL` = version courante.
 *
 * Une **correction de saisie** (pas un vrai changement) met à jour la ligne
 * existante (`UPDATE`) et garde `change_reason = 'input_correction'`. Flux
 * distinct côté UI via un toggle dédié.
 *
 * Colonne `affected_to_exempted_activity_percent` (Phase 1.9, R-2024-022) :
 * pourcentage d'affectation à une activité exonérée. En V1 seule la valeur
 * 100 (affectation totale) déclenche l'exonération - un prorata partiel
 * sera traité en V2 si demandé.
 *
 * Motifs `change_reason` (cf. UI page Edit véhicule mode « Nouvelle version ») :
 *   - `initial_creation`   : création du véhicule
 *   - `recharacterization` : reclassement fiscal
 *   - `regulation_change`  : changement réglementaire
 *   - `other_change`       : autre (`change_note` requis côté UI)
 *   - `input_correction`   : correction de saisie (pas un vrai changement)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_fiscal_characteristics', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('vehicle_id')
                ->constrained('vehicles')
                ->restrictOnDelete();

            $table->date('effective_from');
            $table->date('effective_to')->nullable();

            // Catégorie européenne et type utilisateur
            $table->string('reception_category', 10);
            $table->string('vehicle_user_type', 10);
            $table->string('body_type', 20);
            $table->unsignedSmallInteger('seats_count');

            // Source d'énergie et motorisation
            $table->string('energy_source', 30);
            $table->string('underlying_combustion_engine_type', 20)->nullable();
            $table->string('euro_standard', 20)->nullable();
            $table->string('pollutant_category', 30);

            // Mesure des émissions
            $table->string('homologation_method', 20);
            $table->unsignedSmallInteger('co2_wltp')->nullable();
            $table->unsignedSmallInteger('co2_nedc')->nullable();
            $table->unsignedSmallInteger('taxable_horsepower')->nullable();
            $table->unsignedInteger('kerb_mass')->nullable();

            // Flags fiscaux conditionnels
            $table->boolean('handicap_access')->default(false);
            $table->boolean('n1_passenger_transport')->default(false);
            $table->boolean('n1_removable_second_row_seat')->default(false);
            $table->boolean('m1_special_use')->default(false);
            $table->boolean('n1_ski_lift_use')->default(false);
            $table->unsignedTinyInteger('affected_to_exempted_activity_percent')->default(0);

            // Audit
            $table->string('change_reason', 20);
            $table->text('change_note')->nullable();

            $table->timestamps();

            $table->index(['vehicle_id', 'effective_from']);
            // Index sur effective_to pour accélérer la recherche de la version
            // courante (effective_to IS NULL). La colonne générée is_current
            // initialement prévue est retirée en MVP - Hostinger refuse les
            // expressions conditionnelles dans GENERATED ALWAYS AS.
            $table->index(['vehicle_id', 'effective_to']);
        });

        // CHECK constraints + triggers anti-overlap - MySQL uniquement
        // (SQLite ne supporte pas `ALTER TABLE ... ADD CONSTRAINT` ni
        // SIGNAL/SQLSTATE).
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE vehicle_fiscal_characteristics
                ADD CONSTRAINT chk_vfc_effective_dates_ordered
                CHECK (effective_to IS NULL OR effective_from <= effective_to)
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE vehicle_fiscal_characteristics
                ADD CONSTRAINT chk_vfc_reception_category_enum
                CHECK (reception_category IN ('M1', 'N1'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE vehicle_fiscal_characteristics
                ADD CONSTRAINT chk_vfc_user_type_consistent_with_reception
                CHECK (
                    (reception_category = 'M1' AND vehicle_user_type = 'VP')
                    OR (reception_category = 'N1' AND vehicle_user_type = 'VU')
                )
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE vehicle_fiscal_characteristics
                ADD CONSTRAINT chk_vfc_body_type_enum
                CHECK (body_type IN ('CI', 'BB', 'CTTE', 'BE', 'HB'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE vehicle_fiscal_characteristics
                ADD CONSTRAINT chk_vfc_homologation_method_enum
                CHECK (homologation_method IN ('WLTP', 'NEDC', 'PA'))
        SQL);

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

        DB::statement(<<<'SQL'
            ALTER TABLE vehicle_fiscal_characteristics
                ADD CONSTRAINT chk_vfc_homologation_implies_measurement
                CHECK (
                    (homologation_method = 'WLTP' AND co2_wltp IS NOT NULL)
                    OR (homologation_method = 'NEDC' AND co2_nedc IS NOT NULL)
                    OR (homologation_method = 'PA'   AND taxable_horsepower IS NOT NULL)
                )
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE vehicle_fiscal_characteristics
                ADD CONSTRAINT chk_vfc_pollutant_category_enum
                CHECK (pollutant_category IN ('e', 'category_1', 'most_polluting'))
        SQL);

        $this->createOverlapTriggers();
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            $this->dropOverlapTriggers();
        }

        Schema::dropIfExists('vehicle_fiscal_characteristics');
    }

    private function createOverlapTriggers(): void
    {
        $body = <<<'SQL'
            DECLARE overlap_count INT;

            SELECT COUNT(*) INTO overlap_count
            FROM vehicle_fiscal_characteristics
            WHERE vehicle_id = NEW.vehicle_id
              AND id <> COALESCE(NEW.id, 0)
              AND NEW.effective_from <= COALESCE(effective_to, DATE('9999-12-31'))
              AND effective_from <= COALESCE(NEW.effective_to, DATE('9999-12-31'));

            IF overlap_count > 0 THEN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'vehicle_fiscal_characteristics: overlapping effective period for this vehicle';
            END IF;
        SQL;

        DB::unprepared('CREATE TRIGGER vfc_no_overlap_insert BEFORE INSERT ON vehicle_fiscal_characteristics FOR EACH ROW BEGIN '.$body.' END');
        DB::unprepared('CREATE TRIGGER vfc_no_overlap_update BEFORE UPDATE ON vehicle_fiscal_characteristics FOR EACH ROW BEGIN '.$body.' END');
    }

    private function dropOverlapTriggers(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS vfc_no_overlap_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS vfc_no_overlap_update');
    }
};
