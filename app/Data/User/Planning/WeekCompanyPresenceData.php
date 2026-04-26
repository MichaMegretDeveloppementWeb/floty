<?php

namespace App\Data\User\Planning;

use App\Data\User\Company\CompanyOptionData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Présence d'une entreprise sur la semaine du drawer (entreprise +
 * nombre de jours sur cette semaine).
 */
#[TypeScript]
final class WeekCompanyPresenceData extends Data
{
    public function __construct(
        public CompanyOptionData $company,
        public int $days,
    ) {}
}
