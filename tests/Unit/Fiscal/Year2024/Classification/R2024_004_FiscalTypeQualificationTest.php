<?php

declare(strict_types=1);

namespace Tests\Unit\Fiscal\Year2024\Classification;

use App\Enums\Vehicle\BodyType;
use App\Enums\Vehicle\ReceptionCategory;
use App\Enums\Vehicle\VehicleUserType;
use App\Fiscal\Pipeline\PipelineContext;
use App\Fiscal\Year2024\Classification\R2024_004_FiscalTypeQualification;
use App\Models\Vehicle;
use App\Models\VehicleFiscalCharacteristics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Couvre la cascade de classification fiscale (R-2024-004, CIBS L. 421-2)
 * et le motif d'exclusion qu'elle pose sur le contexte selon la
 * branche d'exclusion. Sans ce motif, l'UI affiche le message
 * générique « Véhicule hors du champ fiscal » qui n'aide pas
 * l'utilisateur à comprendre pourquoi son véhicule particulier est
 * sorti du champ.
 */
final class R2024_004_FiscalTypeQualificationTest extends TestCase
{
    use RefreshDatabase;

    private R2024_004_FiscalTypeQualification $rule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule = new R2024_004_FiscalTypeQualification;
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
    public function camionnette_n1_sans_aucun_flag_est_hors_champ_avec_motif_combine(): void
    {
        $vfc = $this->makeVfc([
            'reception_category' => ReceptionCategory::N1,
            'vehicle_user_type' => VehicleUserType::CommercialVehicle,
            'body_type' => BodyType::LightTruck,
            'n1_removable_second_row_seat' => false,
            'n1_passenger_transport' => false,
        ]);

        $context = $this->rule->classify($this->makeContext($vfc));

        self::assertFalse($context->isFiscallyTaxable);
        self::assertNotNull($context->isFiscallyTaxableReason);
        self::assertStringContainsString('sans 2', $context->isFiscallyTaxableReason);
        self::assertStringContainsString('non affectée au transport de personnes', $context->isFiscallyTaxableReason);
    }

    #[Test]
    public function camionnette_n1_sans_2eme_rangee_seule_est_hors_champ_avec_motif_specifique(): void
    {
        // Cas EB-002-BB : camionnette N1 + transport personnes coché
        // mais sans 2ᵉ rangée amovible.
        $vfc = $this->makeVfc([
            'reception_category' => ReceptionCategory::N1,
            'vehicle_user_type' => VehicleUserType::CommercialVehicle,
            'body_type' => BodyType::LightTruck,
            'n1_removable_second_row_seat' => false,
            'n1_passenger_transport' => true,
        ]);

        $context = $this->rule->classify($this->makeContext($vfc));

        self::assertFalse($context->isFiscallyTaxable);
        self::assertNotNull($context->isFiscallyTaxableReason);
        self::assertStringContainsString('sans 2', $context->isFiscallyTaxableReason);
        self::assertStringNotContainsString('non affectée au transport de personnes', $context->isFiscallyTaxableReason);
    }

    #[Test]
    public function camionnette_n1_sans_transport_personnes_seul_est_hors_champ_avec_motif_specifique(): void
    {
        $vfc = $this->makeVfc([
            'reception_category' => ReceptionCategory::N1,
            'vehicle_user_type' => VehicleUserType::CommercialVehicle,
            'body_type' => BodyType::LightTruck,
            'n1_removable_second_row_seat' => true,
            'n1_passenger_transport' => false,
        ]);

        $context = $this->rule->classify($this->makeContext($vfc));

        self::assertFalse($context->isFiscallyTaxable);
        self::assertNotNull($context->isFiscallyTaxableReason);
        self::assertStringContainsString('non affectée au transport de personnes', $context->isFiscallyTaxableReason);
        self::assertStringNotContainsString('sans 2', $context->isFiscallyTaxableReason);
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
            fiscalYear: 2024,
            daysInYear: 366,
            currentFiscalCharacteristics: $vfc,
        );
    }
}
