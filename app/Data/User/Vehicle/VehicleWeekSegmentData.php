<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use App\Enums\Company\CompanyColor;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One company segment stacked inside a weekly cell of the vehicle usage timeline.
 */
#[TypeScript]
final class VehicleWeekSegmentData extends Data
{
    public function __construct(
        public int $companyId,
        public string $shortCode,
        public CompanyColor $color,
        public int $days,
    ) {}
}
