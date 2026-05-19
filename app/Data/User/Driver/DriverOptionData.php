<?php

declare(strict_types=1);

namespace App\Data\User\Driver;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Minimal driver option for the Contract form selector. Filtered by company
 * and contract period via `DriverQueryService::optionsForContract`.
 */
#[TypeScript]
final class DriverOptionData extends Data
{
    public function __construct(
        public int $id,
        public string $fullName,
        public string $initials,
    ) {}
}
