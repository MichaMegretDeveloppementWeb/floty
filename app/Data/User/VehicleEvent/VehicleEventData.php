<?php

declare(strict_types=1);

namespace App\Data\User\VehicleEvent;

use App\Enums\VehicleEvent\VehicleEventType;
use App\Models\VehicleEvent;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Read-side representation of an unavailability (vehicle Show page,
 * lists, etc.).
 *
 *   - `daysCount`: inclusive day count, or 0 when the unavailability is
 *     still ongoing (end_date null); the frontend then renders "depuis
 *     le {start_date}".
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
        public bool $hasFiscalImpact,
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
            hasFiscalImpact: $u->has_fiscal_impact,
            startDate: $u->start_date->toDateString(),
            endDate: $u->end_date?->toDateString(),
            description: $u->description,
            daysCount: $daysCount,
            documents: $documents,
        );
    }
}
