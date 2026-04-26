<?php

namespace App\Data\User\Vehicle;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Option véhicule pour les `<SelectInput>` des formulaires
 * (Attribution rapide, Drawer, etc.).
 */
#[TypeScript]
final class VehicleOptionData extends Data
{
    public function __construct(
        public int $id,
        public string $licensePlate,
        public string $label,
    ) {}
}
