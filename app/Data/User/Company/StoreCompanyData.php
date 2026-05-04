<?php

declare(strict_types=1);

namespace App\Data\User\Company;

use App\Enums\Company\CompanyColor;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Size;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Payload de création d'une entreprise. Reçu en snake_case depuis le
 * formulaire HTML, mappé en camelCase côté PHP.
 *
 * Validation Spatie Data : règles déclarées via attributs, cumulées
 * avec des règles dynamiques dans `rules()` quand un contexte est
 * nécessaire (ex. unique filtré par soft-delete).
 */
#[TypeScript]
#[MapInputName(SnakeCaseMapper::class)]
final class StoreCompanyData extends Data
{
    public function __construct(
        #[Required, Max(255)]
        public string $legalName,

        #[Required]
        public CompanyColor $color,

        #[Size(9), Regex('/^\d{9}$/')]
        public ?string $siren,

        #[Size(14), Regex('/^\d{14}$/')]
        public ?string $siret,

        #[Max(255)]
        public ?string $addressLine1,

        #[Max(255)]
        public ?string $addressLine2,

        #[Max(10)]
        public ?string $postalCode,

        #[Max(100)]
        public ?string $city,

        #[Required, Size(2)]
        public string $country,

        #[Max(150)]
        public ?string $contactName,

        #[Email, Max(255)]
        public ?string $contactEmail,

        #[Max(30)]
        public ?string $contactPhone,

        public bool $isActive = true,
    ) {}

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'legal_name.required' => 'La raison sociale est obligatoire.',
            'color.required' => 'La couleur est obligatoire.',
            'siren.size' => 'Le SIREN doit contenir exactement 9 chiffres.',
            'siren.regex' => 'Le SIREN doit contenir uniquement des chiffres.',
            'siret.size' => 'Le SIRET doit contenir exactement 14 chiffres.',
            'siret.regex' => 'Le SIRET doit contenir uniquement des chiffres.',
            'contact_email.email' => 'Le format de l\'adresse e-mail est invalide.',
        ];
    }

    /**
     * Défauts métier appliqués avant validation : pays par défaut FR,
     * is_active par défaut true.
     *
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    public static function prepareForPipeline(array $properties): array
    {
        if (empty($properties['country'] ?? null)) {
            $properties['country'] = 'FR';
        }
        if (! array_key_exists('is_active', $properties) || $properties['is_active'] === null) {
            $properties['is_active'] = true;
        }

        return $properties;
    }
}
