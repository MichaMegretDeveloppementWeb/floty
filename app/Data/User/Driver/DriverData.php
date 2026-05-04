<?php

declare(strict_types=1);

namespace App\Data\User\Driver;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Vue détaillée d'un conducteur - utilisée par la page de détail
 * (Phase 06 V1.2 : `User/Drivers/Show/Index.vue`).
 */
#[TypeScript]
final class DriverData extends Data
{
    /**
     * @param  list<DriverCompanyMembershipData>  $memberships
     */
    public function __construct(
        public int $id,
        public string $firstName,
        public string $lastName,
        public string $fullName,
        public string $initials,
        #[DataCollectionOf(DriverCompanyMembershipData::class)]
        public array $memberships,
        public int $contractsCount,
    ) {}
}
