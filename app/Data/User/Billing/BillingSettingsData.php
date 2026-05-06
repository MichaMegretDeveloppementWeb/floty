<?php

declare(strict_types=1);

namespace App\Data\User\Billing;

use App\Models\BillingSettings;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Métadonnées émetteur de facture (Phase 14.G V1.2). Sert à la fois :
 *   - en sortie pour la page Paramètres (lecture)
 *   - en entrée HTTP (validation Spatie via `rules`)
 *
 * Tous les champs sont optionnels — l'utilisateur peut sauvegarder une
 * configuration partielle au démarrage et la compléter plus tard. Le
 * `InvoicePdfRenderer` gère gracieusement les champs manquants.
 */
#[TypeScript]
#[MapInputName(SnakeCaseMapper::class)]
final class BillingSettingsData extends Data
{
    public function __construct(
        #[Nullable, Max(128)]
        public ?string $name,
        // Override explicite — `Str::snake('addressLine1')` produit
        // `address_line1` sans underscore avant le chiffre, mais la
        // colonne et le form utilisent `address_line_1`.
        #[MapInputName('address_line_1'), Nullable, Max(128)]
        public ?string $addressLine1,
        #[MapInputName('address_line_2'), Nullable, Max(128)]
        public ?string $addressLine2,
        #[Nullable, Max(16)]
        public ?string $postalCode,
        #[Nullable, Max(64)]
        public ?string $city,
        #[Nullable, Max(14)]
        public ?string $siren,
        #[Nullable, Email, Max(128)]
        public ?string $contactEmail,
    ) {}

    public static function fromModel(BillingSettings $settings): self
    {
        return new self(
            name: $settings->name,
            addressLine1: $settings->address_line_1,
            addressLine2: $settings->address_line_2,
            postalCode: $settings->postal_code,
            city: $settings->city,
            siren: $settings->siren,
            contactEmail: $settings->contact_email,
        );
    }

    /**
     * Convertit en payload pour le `InvoicePdfRenderer::render()`. Les
     * `null` sont préservés (le template gère l'affichage conditionnel).
     *
     * @return array{name: string, addressLine1?: string|null, addressLine2?: string|null, postalCode?: string|null, city?: string|null, siren?: string|null, contactEmail?: string|null}
     */
    public function toIssuerPayload(): array
    {
        return [
            // Le nom est obligatoire pour le rendu PDF — fallback sur
            // l'app name si vide. Le caller peut afficher un warning UI
            // pour inviter l'utilisateur à compléter ses paramètres.
            'name' => $this->name ?? (string) config('app.name', 'Floty'),
            'addressLine1' => $this->addressLine1,
            'addressLine2' => $this->addressLine2,
            'postalCode' => $this->postalCode,
            'city' => $this->city,
            'siren' => $this->siren,
            'contactEmail' => $this->contactEmail,
        ];
    }
}
