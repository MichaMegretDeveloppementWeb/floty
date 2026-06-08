<?php

declare(strict_types=1);

namespace App\Data\User\Driver;

use App\Enums\Driver\FutureContractsResolutionMode;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Payload for setting `left_at` on a membership and resolving the driver's
 * future contracts within the company.
 *
 * `futureContractsResolution` ·
 *  - 'replace' · `replacementMap` carries a substitute driverId per contract
 *  - 'detach' · all future contracts switch to `driver_id = NULL`
 *  - 'none' · no future contracts to resolve (direct leave)
 *
 * `replacementMap` keys by contractId; values are the replacement driverId
 * (or null to detach individually). Ignored unless resolution is 'replace'.
 */
#[TypeScript]
#[MapInputName(SnakeCaseMapper::class)]
final class LeaveDriverCompanyMembershipData extends Data
{
    /**
     * @param  array<int, ?int>  $replacementMap
     */
    public function __construct(
        #[Required, Date]
        public string $leftAt,

        #[Required]
        public FutureContractsResolutionMode $futureContractsResolution,

        public array $replacementMap = [],
    ) {}

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'left_at.required' => 'La date de sortie est obligatoire.',
            'left_at.date' => 'La date de sortie doit être une date valide.',
            'future_contracts_resolution.required' => 'Choix de résolution des locations à venir obligatoire.',
            'future_contracts_resolution.enum' => 'Résolution invalide.',
        ];
    }
}
