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
 * Invoice issuer metadata. Used as both:
 *   - output for the Settings page (read)
 *   - HTTP input (validation via Spatie `rules`)
 *
 * Every field is optional: the user may save a partial configuration and
 * complete it later. `InvoicePdfRenderer` handles missing fields gracefully.
 */
#[TypeScript]
#[MapInputName(SnakeCaseMapper::class)]
final class BillingSettingsData extends Data
{
    public function __construct(
        #[Nullable, Max(128)]
        public ?string $name,
        // Explicit override: `Str::snake('addressLine1')` produces
        // `address_line1` (no underscore before the digit), but the column
        // and the form use `address_line_1`.
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
     * Build the payload consumed by `InvoicePdfRenderer::render()`. Nulls
     * are preserved (the template handles conditional rendering).
     *
     * @return array{name: string, addressLine1?: string|null, addressLine2?: string|null, postalCode?: string|null, city?: string|null, siren?: string|null, contactEmail?: string|null}
     */
    public function toIssuerPayload(): array
    {
        return [
            // The name is mandatory for the PDF render. If not configured,
            // fall back to an explicit placeholder inviting the user to
            // fill the `Émetteur facture` settings. NEVER "Floty" (Floty
            // is the management tool, not the invoice issuer).
            'name' => $this->name ?? 'Émetteur non configuré',
            'addressLine1' => $this->addressLine1,
            'addressLine2' => $this->addressLine2,
            'postalCode' => $this->postalCode,
            'city' => $this->city,
            'siren' => $this->siren,
            'contactEmail' => $this->contactEmail,
        ];
    }
}
