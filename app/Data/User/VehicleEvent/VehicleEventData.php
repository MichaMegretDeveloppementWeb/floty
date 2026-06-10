<?php

declare(strict_types=1);

namespace App\Data\User\VehicleEvent;

use App\Models\VehicleEvent;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Read-side representation of a vehicle event (vehicle Show page, lists, etc.).
 *
 *   - `title`: free name of the event (always present).
 *   - `categories`: the natures (UI « Nature », free text, at least one);
 *     populated only when the relation is eager-loaded.
 *   - `impliesUnavailability`: drives the "Indisponibilité" badge.
 *   - `hasFiscalImpact`: fiscal-reducer axis, derived at write time from the
 *     reductive natures of the catalogue.
 *   - `daysCount`: inclusive day count, or 0 when the event is still ongoing
 *     (end_date null); the frontend then renders "depuis le {start_date}".
 *   - `documents`: attached evidence (0..5 image or PDF files); populated
 *     only when the relation is eager-loaded.
 */
#[TypeScript]
final class VehicleEventData extends Data
{
    /**
     * @param  list<string>  $categories
     * @param  list<VehicleEventDocumentData>  $documents
     */
    public function __construct(
        public int $id,
        public int $vehicleId,
        public string $title,
        public array $categories,
        public bool $hasFiscalImpact,
        public bool $impliesUnavailability,
        public string $startDate,
        public ?string $endDate,
        public ?string $description,
        /** Optional cost (TTC) in cents attached to this event; null = no cost. */
        public ?int $amountCents,
        public int $daysCount,
        /** System-generated lifecycle marker (acquisition / exit): not editable. */
        public bool $isReadOnly,
        #[DataCollectionOf(VehicleEventDocumentData::class)]
        public array $documents,
    ) {}

    public static function fromModel(VehicleEvent $u): self
    {
        $daysCount = $u->end_date === null
            ? 0
            : ((int) $u->start_date->diffInDays($u->end_date)) + 1;

        // Only attach relations when already loaded; avoids a silent N+1 when
        // the caller forgot `->with('categories')` / `->with('documents')`.
        $categories = $u->relationLoaded('categories')
            ? $u->categories->pluck('category')->values()->all()
            : [];

        $documents = $u->relationLoaded('documents')
            ? $u->documents
                ->map(static fn ($d): VehicleEventDocumentData => VehicleEventDocumentData::fromModel($d))
                ->all()
            : [];

        return new self(
            id: $u->id,
            vehicleId: $u->vehicle_id,
            title: $u->title,
            categories: $categories,
            hasFiscalImpact: $u->has_fiscal_impact,
            impliesUnavailability: $u->implies_unavailability,
            startDate: $u->start_date->toDateString(),
            endDate: $u->end_date?->toDateString(),
            description: $u->description,
            amountCents: $u->amount_cents,
            daysCount: $daysCount,
            isReadOnly: $u->isSystemGenerated(),
            documents: $documents,
        );
    }
}
