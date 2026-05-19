<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use App\Enums\Unavailability\UnavailabilityType;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Active unavailability overlapping a proposed vehicle exit date.
 */
#[TypeScript]
final class ConflictingUnavailabilityData extends Data
{
    public function __construct(
        public int $id,
        public UnavailabilityType $type,
        public string $startDate,
        public string $endDate,
    ) {}
}
