<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use App\Enums\Vehicle\BodyType;
use App\Enums\Vehicle\EnergySource;
use App\Enums\Vehicle\EuroStandard;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\ReceptionCategory;
use App\Enums\Vehicle\UnderlyingCombustionEngineType;
use App\Enums\VehicleRegistryLookup\RegistryLookupDriver;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class VehicleRegistryLookupResultData extends Data
{
    public function __construct(
        public string $licensePlate,
        public ?string $brand,
        public ?string $model,
        public ?string $vin,
        public ?string $color,
        public ?string $firstFrenchRegistrationDate,
        public ?string $firstOriginRegistrationDate,
        public ?ReceptionCategory $receptionCategory,
        public ?BodyType $bodyType,
        public ?int $seatsCount,
        public ?EnergySource $energySource,
        public ?UnderlyingCombustionEngineType $underlyingCombustionEngineType,
        public ?EuroStandard $euroStandard,
        public ?HomologationMethod $homologationMethod,
        public ?int $co2Wltp,
        public ?int $co2Nedc,
        public ?int $taxableHorsepower,
        public ?int $kerbMass,
        public RegistryLookupDriver $sourceDriver,
        public string $fetchedAt,
    ) {}
}
