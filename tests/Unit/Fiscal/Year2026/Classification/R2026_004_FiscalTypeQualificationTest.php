<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\Year2026\Classification;

use App\Enums\Vehicle\BodyType;
use App\Enums\Vehicle\ReceptionCategory;
use App\Enums\Vehicle\VehicleUserType;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\Year2026\Classification\R2026_004_FiscalTypeQualification;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre la cascade de classification fiscale 2026 (R-2026-004, CIBS
 * L. 421-2 stable depuis 01/03/2025) et le motif d'exclusion qu'elle
 * pose sur le contexte. Reconduction stricte de R-2025-004-bis.
 */
final class R2026_004_FiscalTypeQualificationTest extends TestCase
{
    use RefreshDatabase;

    private R2026_004_FiscalTypeQualification $rule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule = new R2026_004_FiscalTypeQualification;
    }

    #[Test]
    public function m1_voiture_particuliere_normale_est_taxable(): void
    {
        $vfc = $this->makeVfc([
            'reception_category' => ReceptionCategory::M1,
            'body_type' => BodyType::InteriorDriving,
            'm1_special_use' => false,
        ]);

        $context = $this->rule->classify($this->makeContext($vfc));

        self::assertTrue($context->isFiscallyTaxable);
        self::assertNull($context->isFiscallyTaxableReason);
    }

    #[Test]
    public function m1_corbillard_avec_special_use_est_hors_champ_avec_motif_specifique(): void
    {
        $vfc = $this->makeVfc([
            'reception_category' => ReceptionCategory::M1,
            'body_type' => BodyType::InteriorDriving,
            'm1_special_use' => true,
        ]);

        $context = $this->rule->classify($this->makeContext($vfc));

        self::assertFalse($context->isFiscallyTaxable);
        self::assertNotNull($context->isFiscallyTaxableReason);
        self::assertStringContainsString('M1 à usage spécial', $context->isFiscallyTaxableReason);
        self::assertStringContainsString('CIBS L. 421-2', $context->isFiscallyTaxableReason);
    }

    #[Test]
    public function pickup_n1_5_places_non_skiable_est_taxable(): void
    {
        $vfc = $this->makeVfc([
            'reception_category' => ReceptionCategory::N1,
            'vehicle_user_type' => VehicleUserType::CommercialVehicle,
            'body_type' => BodyType::Pickup,
            'seats_count' => 5,
            'n1_ski_lift_use' => false,
        ]);

        $context = $this->rule->classify($this->makeContext($vfc));

        self::assertTrue($context->isFiscallyTaxable);
        self::assertNull($context->isFiscallyTaxableReason);
    }

    #[Test]
    public function pickup_n1_skiable_est_hors_champ_avec_motif_remontees_mecaniques(): void
    {
        $vfc = $this->makeVfc([
            'reception_category' => ReceptionCategory::N1,
            'vehicle_user_type' => VehicleUserType::CommercialVehicle,
            'body_type' => BodyType::Pickup,
            'seats_count' => 5,
            'n1_ski_lift_use' => true,
        ]);

        $context = $this->rule->classify($this->makeContext($vfc));

        self::assertFalse($context->isFiscallyTaxable);
        self::assertNotNull($context->isFiscallyTaxableReason);
        self::assertStringContainsString('remontées mécaniques', $context->isFiscallyTaxableReason);
    }

    #[Test]
    public function pickup_n1_4_places_est_hors_champ_avec_motif_moins_de_5_places(): void
    {
        $vfc = $this->makeVfc([
            'reception_category' => ReceptionCategory::N1,
            'vehicle_user_type' => VehicleUserType::CommercialVehicle,
            'body_type' => BodyType::Pickup,
            'seats_count' => 4,
            'n1_ski_lift_use' => false,
        ]);

        $context = $this->rule->classify($this->makeContext($vfc));

        self::assertFalse($context->isFiscallyTaxable);
        self::assertNotNull($context->isFiscallyTaxableReason);
        self::assertStringContainsString('moins de 5 places', $context->isFiscallyTaxableReason);
    }

    #[Test]
    public function camionnette_n1_avec_2_rangs_et_transport_personnes_est_taxable(): void
    {
        $vfc = $this->makeVfc([
            'reception_category' => ReceptionCategory::N1,
            'vehicle_user_type' => VehicleUserType::CommercialVehicle,
            'body_type' => BodyType::LightTruck,
            'n1_removable_second_row_seat' => true,
            'n1_passenger_transport' => true,
        ]);

        $context = $this->rule->classify($this->makeContext($vfc));

        self::assertTrue($context->isFiscallyTaxable);
        self::assertNull($context->isFiscallyTaxableReason);
    }

    #[Test]
    public function n1_avec_carrosserie_autre_est_hors_champ_avec_motif_generique_n1(): void
    {
        $vfc = $this->makeVfc([
            'reception_category' => ReceptionCategory::N1,
            'vehicle_user_type' => VehicleUserType::CommercialVehicle,
            'body_type' => BodyType::StationWagon,
        ]);

        $context = $this->rule->classify($this->makeContext($vfc));

        self::assertFalse($context->isFiscallyTaxable);
        self::assertNotNull($context->isFiscallyTaxableReason);
        self::assertStringContainsString('N1 hors des cas taxables', $context->isFiscallyTaxableReason);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeVfc(array $overrides): VehicleFiscalCharacteristics
    {
        return VehicleFiscalCharacteristics::factory()->create($overrides);
    }

    private function makeContext(VehicleFiscalCharacteristics $vfc): PipelineContext
    {
        return new PipelineContext(
            vehicle: $vfc->vehicle ?? Vehicle::factory()->create(),
            fiscalYear: 2026,
            daysInYear: 365,
            currentFiscalCharacteristics: $vfc,
        );
    }
}
