<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use App\Data\Shared\Listing\PaginationMetaData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Server-side paginated wrapper for the Vehicles Index (ADR-0020).
 */
#[TypeScript]
final class PaginatedVehicleListData extends Data
{
    /**
     * @param  array<int, VehicleListItemData>  $data
     */
    public function __construct(
        public array $data,
        public PaginationMetaData $meta,
    ) {}
}
