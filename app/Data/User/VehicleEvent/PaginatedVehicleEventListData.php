<?php

declare(strict_types=1);

namespace App\Data\User\VehicleEvent;

use App\Data\Shared\Listing\PaginationMetaData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Server-side paginated result wrapper for the global vehicle-events index.
 */
#[TypeScript]
final class PaginatedVehicleEventListData extends Data
{
    /**
     * @param  array<int, VehicleEventListItemData>  $data
     */
    public function __construct(
        public array $data,
        public PaginationMetaData $meta,
    ) {}
}
