<?php

declare(strict_types=1);

namespace App\Data\User\Driver;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Payload de pose de `left_at` sur une membership + résolution des
 * contrats à venir du driver dans cette company (workflow Q6).
 *
 * - `futureContractsResolution` :
 *   - 'replace' : `replacementMap` doit contenir un `replacementDriverId`
 *     pour chaque contrat futur du driver dans cette company
 *   - 'detach' : tous les contrats futurs passent à `driver_id = NULL`
 *   - 'none' : il n'y a pas de contrat futur à résoudre (utilisé quand
 *     la modale a détecté qu'aucun contrat n'est concerné — sortie
 *     directe sans résolution)
 *
 * - `replacementMap` : clé = contractId, valeur = driverId de remplacement
 *   (ou null pour détacher individuellement). Ignoré si resolution !== 'replace'.
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

        #[Required, In(['replace', 'detach', 'none'])]
        public string $futureContractsResolution,

        public array $replacementMap = [],
    ) {}

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'left_at.required' => 'La date de sortie est obligatoire.',
            'future_contracts_resolution.required' => 'Choix de résolution des contrats à venir obligatoire.',
            'future_contracts_resolution.in' => 'Résolution invalide.',
        ];
    }
}
