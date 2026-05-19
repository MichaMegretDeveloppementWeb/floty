<?php

declare(strict_types=1);

namespace App\Data\User\Planning;

use App\Data\User\Company\CompanyOptionData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Presence of a company on the drawer's week (company + day count).
 */
#[TypeScript]
final class WeekCompanyPresenceData extends Data
{
    public function __construct(
        public CompanyOptionData $company,
        public int $days,
    ) {}
}
