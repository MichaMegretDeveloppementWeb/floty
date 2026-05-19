<?php

declare(strict_types=1);

namespace App\Data\User\Driver;

use App\Enums\Company\CompanyColor;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One Driver↔Company membership row from the `driver_company` pivot.
 */
#[TypeScript]
final class DriverCompanyMembershipData extends Data
{
    public function __construct(
        public int $pivotId,
        public int $companyId,
        public string $companyShortCode,
        public string $companyLegalName,
        public CompanyColor $companyColor,
        public string $joinedAt,
        public ?string $leftAt,
        public bool $isCurrentlyActive,
        public int $contractsCount,
    ) {}
}
