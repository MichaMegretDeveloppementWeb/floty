<?php

declare(strict_types=1);

namespace App\Data\User\VehicleEvent;

use App\Enums\VehicleEvent\VehicleEventType;
use App\Models\VehicleEvent;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Slim row for the global vehicle-events index table (all vehicles).
 *
 * Carries just what the listing renders: the owning vehicle plate, the
 * reference date, the type + custom title (the front derives the display
 * label), the categories, and the optional cost. NOT for the detail page
 * (which has its own richer {@see VehicleEventData}).
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
        public VehicleEventType $type,
        public ?string $title,
        public array $categories,
        public bool $impliesUnavailability,
        public bool $isReadOnly,
        public ?int $amountCents,
    ) {}

    public static function fromModel(VehicleEvent $event): self
    {
        return new self(
            id: $event->id,
            vehicleId: $event->vehicle_id,
            vehicleLicensePlate: $event->vehicle->license_plate,
            startDate: $event->start_date->toDateString(),
            type: $event->type,
            title: $event->title,
            categories: $event->categories->pluck('category')->values()->all(),
            impliesUnavailability: $event->implies_unavailability,
            isReadOnly: $event->isSystemGenerated(),
            amountCents: $event->amount_cents,
        );
    }
}
