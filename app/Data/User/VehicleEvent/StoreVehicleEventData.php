<?php

declare(strict_types=1);

namespace App\Data\User\VehicleEvent;

use App\Enums\VehicleEvent\VehicleEventType;
use App\Rules\Vehicle\AvailableForPeriod;
use App\Support\VehicleEvent\EventCategoryList;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Support\Validation\ValidationContext;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Create vehicle-event payload.
 *
 * `has_fiscal_impact` is never carried by the payload; the Action derives it
 * from the enum via `VehicleEventType::isFiscallyReductive()`. `title` is
 * required only for the `other` (custom) type and dropped by the Action for
 * known types; `implies_unavailability` is forced true for known types (only
 * `other` may opt out).
 *
 * `categories` are the user-supplied categories (free text + suggestions). For
 * a known type the Action prepends its default category, so the form may add up
 * to {@see EventCategoryList::MAX} - 1 more; for `other` the user provides 1 to
 * {@see EventCategoryList::MAX}. The Action composes / dedups / caps the final
 * list (the rules here are the per-context guard for clean errors).
 */
#[TypeScript]
#[MapInputName(SnakeCaseMapper::class)]
final class StoreVehicleEventData extends Data
{
    /**
     * `title` / `categories` are nullable WITHOUT a default on purpose: a Spatie
     * Data property with a default is validated as `sometimes` (skipped when
     * absent), which would defeat the `required` / `required_if` rules below.
     *
     * @param  list<string>|null  $categories
     */
    public function __construct(
        #[Required, IntegerType, Exists('vehicles', 'id')]
        public int $vehicleId,

        #[Required]
        public VehicleEventType $type,

        #[Required, Date]
        public string $startDate,

        #[Date]
        public ?string $endDate,

        public ?string $description,

        /** Free name; required (via rules) and kept only for the `other` type. */
        public ?string $title,

        /** User-supplied categories; the Action composes them with the type default. */
        public ?array $categories,

        /** Informative unavailability flag; forced true for known types. */
        public bool $impliesUnavailability = true,

        /** Optional cost (TTC) in cents; costs only, never a revenue. */
        public ?int $amountCents = null,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(ValidationContext $context): array
    {
        $payload = $context->payload;
        $isOther = ($payload['type'] ?? null) === VehicleEventType::Other->value;

        // Custom name required only for `other`. Categories: `other` provides 1
        // to MAX; a known type prepends its default, so at most MAX - 1 extra.
        $rules = [
            'title' => ['nullable', 'string', 'max:120', 'required_if:type,other'],
            'implies_unavailability' => ['boolean'],
            'amount_cents' => ['nullable', 'integer', 'min:0'],
            'categories' => $isOther
                ? ['required', 'array', 'min:1', 'max:'.EventCategoryList::MAX]
                : ['nullable', 'array', 'max:'.(EventCategoryList::MAX - 1)],
            'categories.*' => ['string', 'max:60', 'distinct:ignore_case'],
            // Justification files attached during creation (atomic flow):
            // up to 5 image/PDF files, 5 MB each.
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
            $end = CarbonImmutable::parse($endDate !== '' ? $endDate : $startDate);
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
            'start_date.required' => 'La date de début est obligatoire.',
            'end_date.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
            'categories.required' => 'Au moins une catégorie est obligatoire.',
            'categories.min' => 'Au moins une catégorie est obligatoire.',
            'categories.max' => 'Vous ne pouvez pas dépasser 5 catégories.',
            'categories.*.distinct' => 'Cette catégorie est déjà présente.',
            'categories.*.max' => 'Une catégorie ne peut pas dépasser 60 caractères.',
            'documents.max' => 'Vous ne pouvez joindre que 5 documents au maximum.',
            'documents.*.mimes' => 'Format invalide · seuls les fichiers PDF, JPG, PNG et WebP sont acceptés.',
            'documents.*.max' => 'Fichier trop volumineux · 5 Mo maximum.',
        ];
    }
}
