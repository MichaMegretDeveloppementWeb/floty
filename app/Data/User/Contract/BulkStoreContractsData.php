<?php

declare(strict_types=1);

namespace App\Data\User\Contract;

use App\Actions\Contract\BulkCreateContractsAction;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\AfterOrEqual;
use Spatie\LaravelData\Attributes\Validation\ArrayType;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Payload de création multiple de contrats — création groupée depuis
 * la page planning.
 *
 * **Forme** : une plage commune `[start_date, end_date]` + un type
 * + une référence optionnelle, et la liste des `vehicleIds` à attribuer
 * à la `companyId`. Une transaction unique en {@see BulkCreateContractsAction}
 * crée N contrats en bloc, avec rollback complet si l'un des inserts
 * échoue (notamment via le trigger anti-overlap MySQL).
 */
#[TypeScript]
#[MapInputName(SnakeCaseMapper::class)]
final class BulkStoreContractsData extends Data
{
    /**
     * @param  list<int>  $vehicleIds
     */
    public function __construct(
        #[Required, ArrayType, Min(1), Max(100)]
        public array $vehicleIds,

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

        #[Max(5000)]
        public ?string $notes,
    ) {}

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'vehicle_ids.array' => 'La liste des véhicules est invalide.',
            'vehicle_ids.min' => 'Sélectionnez au moins un véhicule.',
            'vehicle_ids.max' => 'Création limitée à 100 contrats par opération.',
            'company_id.exists' => 'Entreprise introuvable.',
            'driver_id.exists' => 'Conducteur introuvable.',
            'end_date.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
        ];
    }
}
