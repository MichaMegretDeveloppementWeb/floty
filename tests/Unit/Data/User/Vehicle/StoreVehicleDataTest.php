<?php

declare(strict_types=1);

namespace Tests\Unit\Data\User\Vehicle;

use App\Data\User\Vehicle\StoreVehicleData;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests sur la normalisation appliquée pré-validation par
 * {@see StoreVehicleData::prepareForPipeline()}.
 */
final class StoreVehicleDataTest extends TestCase
{
    #[Test]
    public function uppercase_la_plaque_d_immatriculation(): void
    {
        $properties = StoreVehicleData::prepareForPipeline([
            'license_plate' => 'ab-123-cd',
        ]);

        self::assertSame('AB-123-CD', $properties['license_plate']);
    }

    #[Test]
    public function laisse_inchangee_une_plaque_deja_majuscule(): void
    {
        $properties = StoreVehicleData::prepareForPipeline([
            'license_plate' => 'AB-123-CD',
        ]);

        self::assertSame('AB-123-CD', $properties['license_plate']);
    }

    #[Test]
    public function ignore_l_absence_de_plaque(): void
    {
        $properties = StoreVehicleData::prepareForPipeline([]);

        self::assertArrayNotHasKey('license_plate', $properties);
    }

    #[Test]
    public function ignore_une_plaque_non_string(): void
    {
        $properties = StoreVehicleData::prepareForPipeline([
            'license_plate' => null,
        ]);

        self::assertNull($properties['license_plate']);
    }
}
