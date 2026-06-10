<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Active unavailability overlapping a proposed vehicle exit date.
 */
#[TypeScript]
final class ConflictingVehicleEventData extends Data
{
    public function __construct(
        public int $id,
        public string $title,
        public string $startDate,
        public string $endDate,
    ) {}
}
