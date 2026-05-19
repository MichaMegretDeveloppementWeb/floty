<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Versioned fiscal characteristics per vehicle. Each effective change adds a new row
 * (previous row's effective_to is closed). Overlaps are forbidden via SIGNAL triggers.
 * effective_to IS NULL marks the current version.
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

            $table->string('reception_category', 10);
            $table->string('vehicle_user_type', 10);
            $table->string('body_type', 20);
            $table->unsignedSmallInteger('seats_count');

            $table->string('energy_source', 30);
            $table->string('underlying_combustion_engine_type', 20)->nullable();
            $table->string('euro_standard', 20)->nullable();
            $table->string('pollutant_category', 30);

            $table->string('homologation_method', 20);
            $table->unsignedSmallInteger('co2_wltp')->nullable();
            $table->unsignedSmallInteger('co2_nedc')->nullable();
            $table->boolean('accepts_e85')->default(false);
            $table->unsignedSmallInteger('taxable_horsepower')->nullable();
            $table->unsignedInteger('kerb_mass')->nullable();

            $table->boolean('handicap_access')->default(false);
            $table->boolean('n1_passenger_transport')->default(false);
            $table->boolean('n1_removable_second_row_seat')->default(false);
            $table->boolean('m1_special_use')->default(false);
            $table->boolean('n1_ski_lift_use')->default(false);

            $table->string('change_reason', 20);
            $table->text('change_note')->nullable();

            $table->timestamps();

            $table->index(['vehicle_id', 'effective_from']);
            $table->index(['vehicle_id', 'effective_to']);
        });

        // CHECK constraints + anti-overlap triggers: MySQL only.
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
