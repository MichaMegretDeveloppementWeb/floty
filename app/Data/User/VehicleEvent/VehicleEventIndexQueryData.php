<?php

declare(strict_types=1);

namespace App\Data\User\VehicleEvent;

use App\Data\Shared\Listing\IndexQueryData;
use App\Data\Shared\Listing\SortDirection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Query input for the server-side global vehicle-events index (all vehicles).
 *
 * Multi-value filter (OR within the axis, AND with the year):
 *  - `categories`: natures (UI « Nature ») · case-insensitive match on the
 *    `vehicle_event_categories` child rows.
 *  - `year`: events whose reference date (`start_date`) falls in that year.
 *
 * Allowed sort keys: `startDate | vehicle | title | amount` (all pure SQL).
 */
#[TypeScript]
final class VehicleEventIndexQueryData extends IndexQueryData
{
    /**
     * @param  list<string>|null  $categories
     */
    public function __construct(
        public ?array $categories = null,
        public ?int $year = null,
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
        return ['startDate', 'vehicle', 'title', 'amount'];
    }

    public static function rules(): array
    {
        return array_merge(parent::rules(), [
            'categories' => ['nullable', 'array'],
            'categories.*' => ['string', 'max:60'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
        ]);
    }
}
