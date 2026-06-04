<?php

declare(strict_types=1);

namespace App\Data\User\VehicleEvent;

use App\Enums\VehicleEvent\VehicleEventType;
use App\Rules\Vehicle\AvailableForPeriod;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\AfterOrEqual;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Support\Validation\ValidationContext;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Create vehicle-event payload.
 *
 * `has_fiscal_impact` is never carried by the payload; the Action derives it
 * from the enum via `VehicleEventType::isFiscallyReductive()`. `title` /
 * `category` are required only for the `other` (custom) type and dropped by
 * the Action for known types; `implies_unavailability` is forced true for
 * known types (only `other` may opt out).
 */
#[TypeScript]
#[MapInputName(SnakeCaseMapper::class)]
final class StoreVehicleEventData extends Data
{
    public function __construct(
        #[Required, IntegerType, Exists('vehicles', 'id')]
        public int $vehicleId,

        #[Required]
        public VehicleEventType $type,

        #[Required, Date]
        public string $startDate,

        #[Date, AfterOrEqual('start_date')]
        public ?string $endDate,

        #[Max(500)]
        public ?string $description,

        /** Free name; required (via rules) and kept only for the `other` type. */
        public ?string $title,

        /** Free category; required (via rules) and kept only for the `other` type. */
        public ?string $category,

        /** Informative unavailability flag; forced true for known types. */
        public bool $impliesUnavailability = true,
    ) {}

    /**
     * Dynamic rule: when the vehicle has left the fleet (`exit_date` is
     * set) any unavailability that overlaps or extends past that date is
     * blocked (ADR-0018 § 5).
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(ValidationContext $context): array
    {
        $payload = $context->payload;

        // Custom identity is required only for the `other` type; the
        // informative flag is a plain boolean (normalised by the Action).
        $rules = [
            'title' => ['nullable', 'string', 'max:120', 'required_if:type,other'],
            'category' => ['nullable', 'string', 'max:60', 'required_if:type,other'],
            'implies_unavailability' => ['boolean'],
            // Justification files attached during creation (atomic flow):
            // up to 5 image/PDF files, 5 MB each. Same limit and types as
            // the standalone upload endpoint.
            'documents' => ['nullable', 'array', 'max:5'],
            'documents.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ];

        $vehicleId = (int) ($payload['vehicle_id'] ?? 0);
        $startDate = (string) ($payload['start_date'] ?? '');
        $endDate = (string) ($payload['end_date'] ?? $startDate);

        if ($vehicleId === 0 || $startDate === '') {
            return $rules;
        }

        try {
            $start = CarbonImmutable::parse($startDate);
            $end = CarbonImmutable::parse($endDate ?: $startDate);
        } catch (\Exception) {
            return $rules;
        }

        // Restating nullable + after_or_equal because Spatie's `rules()`
        // replaces attribute rules for the listed key; AvailableForPeriod
        // (ADR-0018 exit-date guard) is added alongside.
        $rules['end_date'] = [
            'nullable',
            'date',
            'after_or_equal:start_date',
            new AvailableForPeriod($vehicleId, $start, $end),
        ];

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'vehicle_id.required' => 'Le véhicule est obligatoire.',
            'vehicle_id.exists' => 'Ce véhicule est introuvable.',
            'type.required' => "Le type d'événement est obligatoire.",
            'title.required_if' => "Le nom de l'événement est obligatoire pour le type « Personnalisé ».",
            'category.required_if' => 'La catégorie est obligatoire pour le type « Personnalisé ».',
            'start_date.required' => 'La date de début est obligatoire.',
            'end_date.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
            'documents.max' => 'Vous ne pouvez joindre que 5 documents au maximum.',
            'documents.*.mimes' => 'Format invalide · seuls les fichiers PDF, JPG, PNG et WebP sont acceptés.',
            'documents.*.max' => 'Fichier trop volumineux · 5 Mo maximum.',
        ];
    }
}
