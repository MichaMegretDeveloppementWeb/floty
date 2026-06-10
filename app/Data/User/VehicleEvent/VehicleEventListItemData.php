<?php

declare(strict_types=1);

namespace App\Data\User\VehicleEvent;

use App\Models\VehicleEvent;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Slim row for the global vehicle-events index table (all vehicles).
 *
 * Carries just what the listing renders: the owning vehicle plate, the
 * reference date, the free name, the natures, and the optional cost. NOT for
 * the detail page (which has its own richer {@see VehicleEventData}).
 */
#[TypeScript]
final class VehicleEventListItemData extends Data
{
    /**
     * @param  list<string>  $categories
     */
    public function __construct(
        public int $id,
        public int $vehicleId,
        public string $vehicleLicensePlate,
        public string $startDate,
        public ?string $endDate,
        /** Inclusive day count, or 0 when ongoing (end_date null). */
        public int $daysCount,
        public string $title,
        public array $categories,
        public bool $impliesUnavailability,
        public bool $isReadOnly,
        public ?int $amountCents,
    ) {}

    public static function fromModel(VehicleEvent $event): self
    {
        $daysCount = $event->end_date === null
            ? 0
            : ((int) $event->start_date->diffInDays($event->end_date)) + 1;

        return new self(
            id: $event->id,
            vehicleId: $event->vehicle_id,
            vehicleLicensePlate: $event->vehicle->license_plate,
            startDate: $event->start_date->toDateString(),
            endDate: $event->end_date?->toDateString(),
            daysCount: $daysCount,
            title: $event->title,
            categories: $event->categories->pluck('category')->values()->all(),
            impliesUnavailability: $event->implies_unavailability,
            isReadOnly: $event->isSystemGenerated(),
            amountCents: $event->amount_cents,
        );
    }
}
