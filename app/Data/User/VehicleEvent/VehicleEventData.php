<?php

declare(strict_types=1);

namespace App\Data\User\VehicleEvent;

use App\Enums\VehicleEvent\VehicleEventType;
use App\Models\VehicleEvent;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Read-side representation of a vehicle event (vehicle Show page, lists, etc.).
 *
 *   - `title` / `category`: custom identity, present only for the `other`
 *     type; null for known types (the frontend derives label and category
 *     from the enum).
 *   - `impliesUnavailability`: drives the "Indisponibilité" badge.
 *   - `hasFiscalImpact`: independent fiscal-reducer axis (never true for `other`).
 *   - `daysCount`: inclusive day count, or 0 when the event is still ongoing
 *     (end_date null); the frontend then renders "depuis le {start_date}".
 *   - `documents`: attached evidence (0..5 image or PDF files); populated
 *     only when the relation is eager-loaded.
 */
#[TypeScript]
final class VehicleEventData extends Data
{
    /**
     * @param  list<VehicleEventDocumentData>  $documents
     */
    public function __construct(
        public int $id,
        public int $vehicleId,
        public VehicleEventType $type,
        public ?string $title,
        public ?string $category,
        public bool $hasFiscalImpact,
        public bool $impliesUnavailability,
        public string $startDate,
        public ?string $endDate,
        public ?string $description,
        public int $daysCount,
        #[DataCollectionOf(VehicleEventDocumentData::class)]
        public array $documents,
    ) {}

    public static function fromModel(VehicleEvent $u): self
    {
        $daysCount = $u->end_date === null
            ? 0
            : ((int) $u->start_date->diffInDays($u->end_date)) + 1;

        // Only attach documents when the relation is already loaded;
        // avoids a silent N+1 when the caller forgot `->with('documents')`.
        $documents = $u->relationLoaded('documents')
            ? $u->documents
                ->map(static fn ($d): VehicleEventDocumentData => VehicleEventDocumentData::fromModel($d))
                ->all()
            : [];

        return new self(
            id: $u->id,
            vehicleId: $u->vehicle_id,
            type: $u->type,
            title: $u->title,
            category: $u->category,
            hasFiscalImpact: $u->has_fiscal_impact,
            impliesUnavailability: $u->implies_unavailability,
            startDate: $u->start_date->toDateString(),
            endDate: $u->end_date?->toDateString(),
            description: $u->description,
            daysCount: $daysCount,
            documents: $documents,
        );
    }
}
