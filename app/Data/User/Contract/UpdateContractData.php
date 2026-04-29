<?php

declare(strict_types=1);

namespace App\Data\User\Contract;

use App\Enums\Contract\ContractType;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\AfterOrEqual;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Payload d'édition d'un contrat (chantier 04.G — page Edit).
 *
 * Sémantique d'avenant : modifier un contrat met à jour ses bornes en
 * place. Pas d'historique d'avenant en V1 — la traçabilité passera par
 * les documents joints (cf. ADR-0014 D6 et chantier 04.K).
 *
 * Mêmes validations que {@see StoreContractData}. Note : le trigger
 * MySQL anti-overlap exclut déjà la ligne courante via `id <> NEW.id`.
 */
#[TypeScript]
#[MapInputName(SnakeCaseMapper::class)]
final class UpdateContractData extends Data
{
    public function __construct(
        #[Required, IntegerType, Exists('vehicles', 'id')]
        public int $vehicleId,

        #[Required, IntegerType, Exists('companies', 'id')]
        public int $companyId,

        #[IntegerType, Exists('drivers', 'id')]
        public ?int $driverId,

        #[Required, Date]
        public string $startDate,

        #[Required, Date, AfterOrEqual('start_date')]
        public string $endDate,

        #[Max(64)]
        public ?string $contractReference,

        #[Required]
        public ContractType $contractType,

        #[Max(5000)]
        public ?string $notes,
    ) {}

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'vehicle_id.exists' => 'Véhicule introuvable.',
            'company_id.exists' => 'Entreprise introuvable.',
            'driver_id.exists' => 'Conducteur introuvable.',
            'end_date.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
        ];
    }
}
