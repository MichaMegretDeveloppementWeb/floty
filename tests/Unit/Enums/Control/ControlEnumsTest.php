<?php

declare(strict_types=1);

namespace Tests\Unit\Enums\Control;

use App\Enums\Control\ControlAnchor;
use App\Enums\Control\DurationUnit;
use App\Enums\Control\RecipientLevel;
use App\Enums\Control\RecipientOperation;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests Unit des enums du domaine Contrôles (Chantier B).
 */
final class ControlEnumsTest extends TestCase
{
    #[Test]
    public function control_anchor_mappe_chaque_cas_vers_une_colonne_vehicule(): void
    {
        self::assertSame('first_origin_registration_date', ControlAnchor::FirstOriginRegistration->vehicleColumn());
        self::assertSame('first_french_registration_date', ControlAnchor::FirstFrenchRegistration->vehicleColumn());
        self::assertSame('acquisition_date', ControlAnchor::Acquisition->vehicleColumn());
        self::assertSame('first_economic_use_date', ControlAnchor::EconomicUse->vehicleColumn());
    }

    #[Test]
    public function control_anchor_a_des_labels_francais_non_vides_sans_em_dash(): void
    {
        foreach (ControlAnchor::cases() as $case) {
            self::assertNotSame('', $case->label());
            self::assertStringNotContainsString("\u{2014}", $case->label());
        }
    }

    #[Test]
    public function duration_unit_expose_valeurs_et_labels(): void
    {
        self::assertSame('years', DurationUnit::Years->value);
        self::assertSame('months', DurationUnit::Months->value);
        self::assertSame('ans', DurationUnit::Years->label());
        self::assertSame('mois', DurationUnit::Months->label());
    }

    #[Test]
    public function recipient_level_et_operation_exposent_valeurs_et_labels(): void
    {
        self::assertSame('settings', RecipientLevel::Settings->value);
        self::assertSame('definition', RecipientLevel::Definition->value);
        self::assertSame('vehicle', RecipientLevel::Vehicle->value);
        self::assertSame('include', RecipientOperation::Include->value);
        self::assertSame('exclude', RecipientOperation::Exclude->value);

        foreach (RecipientLevel::cases() as $level) {
            self::assertNotSame('', $level->label());
        }
        foreach (RecipientOperation::cases() as $operation) {
            self::assertNotSame('', $operation->label());
        }
    }
}
