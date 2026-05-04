<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use App\Data\Shared\Listing\IndexQueryData;
use App\Data\Shared\Listing\SortDirection;
use App\Enums\Vehicle\VehicleStatus;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * DTO d'entrée pour l'Index Vehicles server-side (cf. ADR-0020).
 *
 * Filtres spécifiques :
 *  - `includeExited: bool` (défaut false) — inclut les véhicules dont
 *     `exit_date` est passée (cf. ADR-0018 § 4)
 *  - `status: VehicleStatus|null` — filtre par statut courant
 *
 * Whitelist sortKey : `licensePlate | model | firstFrenchRegistrationDate
 * | acquisitionDate | currentStatus`. La colonne `fullYearTax` est
 * volontairement exclue : valeur calculée par l'aggregator fiscal
 * multi-règles, non triable en SQL pure (cf. ADR-0020 D6).
 */
#[TypeScript]
final class VehicleIndexQueryData extends IndexQueryData
{
    public function __construct(
        public bool $includeExited = false,
        public ?VehicleStatus $status = null,
        int $page = 1,
        int $perPage = self::DEFAULT_PER_PAGE,
        ?string $search = null,
        ?string $sortKey = null,
        SortDirection $sortDirection = SortDirection::Asc,
    ) {
        parent::__construct($page, $perPage, $search, $sortKey, $sortDirection);
    }

    public static function allowedSortKeys(): array
    {
        return [
            'licensePlate',
            'model',
            'firstFrenchRegistrationDate',
            'acquisitionDate',
            'currentStatus',
        ];
    }

    public static function rules(): array
    {
        return array_merge(parent::rules(), [
            'includeExited' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', 'in:active,maintenance,sold,destroyed,other'],
        ]);
    }
}
