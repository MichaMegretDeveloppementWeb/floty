<?php

declare(strict_types=1);

namespace App\Data\User\Driver;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Payload for editing an existing Driver↔Company membership.
 *
 * Three use cases share this endpoint: correcting `joined_at` only,
 * editing both dates simultaneously, or reactivating a closed membership
 * by sending `left_at: null`. The chronological invariant
 * (`joined_at <= left_at`) is enforced at runtime by the update action,
 * since the conditional rule is not expressible as a Spatie attribute.
 *
 * Creating an exit (with future-contracts resolution) remains driven by
 * {@see LeaveDriverCompanyMembershipData}.
 */
#[TypeScript]
#[MapInputName(SnakeCaseMapper::class)]
final class UpdateDriverCompanyMembershipData extends Data
{
    public function __construct(
        #[Required, Date]
        public string $joinedAt,

        #[Nullable, Date]
        public ?string $leftAt = null,
    ) {}

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'joined_at.required' => "La date d'entrée est obligatoire.",
            'joined_at.date' => "La date d'entrée doit être une date valide.",
            'left_at.date' => 'La date de sortie doit être une date valide.',
        ];
    }
}
