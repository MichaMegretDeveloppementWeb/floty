<?php

declare(strict_types=1);

namespace App\Data\User\Contract;

use App\Enums\Company\CompanyColor;
use App\Enums\Contract\ContractType;
use App\Models\Contract;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Vue liste d'un contrat - utilisée par la table de la page
 * `User/Contracts/Index/Index.vue` (chantier 04.G). Champs essentiels
 * uniquement pour limiter le payload Inertia.
 */
#[TypeScript]
final class ContractListItemData extends Data
{
    public function __construct(
        public int $id,
        public int $vehicleId,
        public string $vehicleLicensePlate,
        public bool $vehicleIsExited,
        public int $companyId,
        public string $companyShortCode,
        public string $companyLegalName,
        public CompanyColor $companyColor,
        public ?int $driverId,
        public ?string $driverFullName,
        public ?string $driverInitials,
        public string $startDate,
        public string $endDate,
        public int $durationDays,
        public ContractType $contractType,
        public ?string $contractReference,
    ) {}

    public static function fromModel(Contract $contract): self
    {
        $start = $contract->start_date;
        $end = $contract->end_date;

        $duration = (int) $start->diffInDays($end) + 1;

        $driverFullName = null;
        $driverInitials = null;
        if ($contract->driver !== null) {
            $first = (string) ($contract->driver->first_name ?? '');
            $last = (string) ($contract->driver->last_name ?? '');
            $driverFullName = trim($first.' '.$last);
            if ($driverFullName === '') {
                $driverFullName = null;
            } else {
                $driverInitials = mb_strtoupper(mb_substr($first, 0, 1).mb_substr($last, 0, 1));
            }
        }

        return new self(
            id: $contract->id,
            vehicleId: $contract->vehicle_id,
            vehicleLicensePlate: $contract->vehicle->license_plate,
            vehicleIsExited: $contract->vehicle->is_exited,
            companyId: $contract->company_id,
            companyShortCode: $contract->company->short_code,
            companyLegalName: $contract->company->legal_name,
            companyColor: $contract->company->color,
            driverId: $contract->driver_id,
            driverFullName: $driverFullName,
            driverInitials: $driverInitials,
            startDate: $start->toDateString(),
            endDate: $end->toDateString(),
            durationDays: $duration,
            contractType: $contract->contract_type,
            contractReference: $contract->contract_reference,
        );
    }
}
