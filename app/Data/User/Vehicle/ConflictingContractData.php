<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Active contract overlapping a proposed vehicle exit date.
 */
#[TypeScript]
final class ConflictingContractData extends Data
{
    public function __construct(
        public int $id,
        public string $companyShortCode,
        public string $startDate,
        public string $endDate,
    ) {}
}
