<?php

declare(strict_types=1);

namespace App\Data\User\VehicleEvent;

use App\Rules\Vehicle\AvailableForPeriod;
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
 * Create vehicle-event payload (refonte type → nature).
 *
 * `title` is the free name of the event, always required. `categories` are
 * the natures (UI « Nature », unlimited, at least one): free text + catalogue
 * suggestions. `has_fiscal_impact` is never carried by the payload; the
 * Action derives it from the reductive natures of the catalogue
 * ({@see App\Services\VehicleEvent\EventNatureFiscalResolver}) and forces
 * `implies_unavailability` to true when the event is reductive.
 */
#[TypeScript]
#[MapInputName(SnakeCaseMapper::class)]
final class StoreVehicleEventData extends Data
{
    /**
     * `categories` is nullable WITHOUT a default on purpose: a Spatie Data
     * property with a default is validated as `sometimes` (skipped when
     * absent), which would defeat the `required` rule below.
     *
     * @param  list<string>|null  $categories
     * @param  list<string>|null  $details
     */
    public function __construct(
        #[Required, IntegerType, Exists('vehicles', 'id')]
        public int $vehicleId,

        /** Free name of the event, always required. */
        #[Required]
        public string $title,

        #[Required, Date]
        public string $startDate,

        #[Date]
        public ?string $endDate,

        public ?string $description,

        /** Natures of the event (UI « Nature »), at least one. */
        public ?array $categories,

        /** Detail lines (section « Détails »), optional, unlimited. */
        public ?array $details = null,

        /** Garage name (free text, optional); feeds the autocomplete. */
        public ?string $garage = null,

        /** Postal code (optional, independent from the garage). */
        public ?string $postalCode = null,

        /** Unavailability flag; forced true server-side when reductive. */
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

        $rules = [
            'title' => ['required', 'string', 'max:120'],
            'implies_unavailability' => ['boolean'],
            'amount_cents' => ['nullable', 'integer', 'min:0'],
            'categories' => ['required', 'array', 'min:1'],
            'categories.*' => ['string', 'max:60', 'distinct:ignore_case'],
            'details' => ['nullable', 'array'],
            'details.*' => ['string', 'max:100', 'distinct:ignore_case'],
            'garage' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:10'],
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
            'vehicle_id.numeric' => 'Véhicule invalide.',
            'vehicle_id.integer' => 'Véhicule invalide.',
            'vehicle_id.exists' => 'Ce véhicule est introuvable.',
            'title.required' => "Le nom de l'événement est obligatoire.",
            'title.max' => "Le nom de l'événement ne doit pas dépasser :max caractères.",
            'start_date.required' => 'La date de début est obligatoire.',
            'start_date.date' => 'La date de début doit être une date valide.',
            'end_date.date' => 'La date de fin doit être une date valide.',
            'end_date.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
            'categories.required' => 'Au moins une nature est obligatoire.',
            'categories.min' => 'Au moins une nature est obligatoire.',
            'categories.*.distinct' => 'Cette nature est déjà présente.',
            'categories.*.max' => 'Une nature ne peut pas dépasser 60 caractères.',
            'details.*.distinct' => 'Ce détail est déjà présent.',
            'details.*.max' => 'Un détail ne peut pas dépasser 100 caractères.',
            'garage.max' => 'Le nom du garage ne peut pas dépasser 120 caractères.',
            'postal_code.max' => 'Le code postal ne peut pas dépasser 10 caractères.',
            'amount_cents.numeric' => 'Le montant doit être un nombre.',
            'amount_cents.integer' => 'Le montant doit être un nombre entier.',
            'amount_cents.min' => 'Le montant ne peut pas être négatif.',
            'documents.max' => 'Vous ne pouvez joindre que 5 documents au maximum.',
            'documents.*.file' => 'Chaque pièce jointe doit être un fichier valide.',
            'documents.*.mimes' => 'Format invalide · seuls les fichiers PDF, JPG, PNG et WebP sont acceptés.',
            'documents.*.max' => 'Fichier trop volumineux · 5 Mo maximum.',
        ];
    }
}
