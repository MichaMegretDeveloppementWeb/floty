<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use App\Data\Shared\Listing\IndexQueryData;
use App\Data\Shared\Listing\SortDirection;
use App\Enums\Vehicle\EnergySource;
use App\Enums\Vehicle\PollutantCategory;
use App\Enums\Vehicle\VehicleStatus;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * DTO d'entrée pour l'Index Vehicles server-side (cf. ADR-0020).
 *
 * Filtres :
 *  - `includeExited: bool` (défaut **true**) — inclut les véhicules dont
 *     `exit_date` est passée. Décision UX : par défaut on affiche tous
 *     les véhicules historiques pour permettre la consultation et
 *     l'édition rétroactive (cf. ADR-0018 § 4). L'utilisateur peut
 *     décocher pour ne voir que les véhicules actifs aujourd'hui.
 *  - `status: VehicleStatus|null` — filtre par statut courant
 *  - `energySource: EnergySource|null` — sur la VFC active
 *  - `pollutantCategory: PollutantCategory|null` — sur la VFC active
 *  - `handicapAccess: bool|null` — sur la VFC active (true = uniquement
 *     les véhicules accessibles handicapés)
 *  - `firstRegistrationYearMin/Max: int|null` —
 *     `YEAR(first_french_registration_date)` dans la fourchette
 *     (date de 1ʳᵉ immatriculation, plus pertinente que la date
 *     d'acquisition pour borner l'âge fiscal du véhicule)
 *
 * Dimension annuelle :
 *  - `year: int|null` — année qui pilote les colonnes financières de la
 *     table (Coût plein, Prix location). Préfigure le pattern « année
 *     par page » de l'ADR-0020 (chantier η à venir). Null → fallback
 *     `FiscalYearResolver` côté controller. Borné par `available_years`.
 *
 * Whitelist sortKey : `licensePlate | model | firstFrenchRegistrationDate
 * | acquisitionDate | currentStatus`. La colonne `fullYearTax` est
 * volontairement exclue : valeur calculée par l'aggregator fiscal
 * multi-règles, non triable en SQL pure (cf. ADR-0020 D6).
 */
#[TypeScript]
final class VehicleIndexQueryData extends IndexQueryData
{
    public function __construct(
        public bool $includeExited = true,
        public ?VehicleStatus $status = null,
        public ?EnergySource $energySource = null,
        public ?PollutantCategory $pollutantCategory = null,
        public ?bool $handicapAccess = null,
        public ?int $firstRegistrationYearMin = null,
        public ?int $firstRegistrationYearMax = null,
        public ?int $year = null,
        int $page = 1,
        int $perPage = self::DEFAULT_PER_PAGE,
        ?string $search = null,
        ?string $sortKey = null,
        SortDirection $sortDirection = SortDirection::Asc,
    ) {
        parent::__construct($page, $perPage, $search, $sortKey, $sortDirection);
    }

    public static function allowedSortKeys(): array
    {
        return [
            'licensePlate',
            'model',
            'firstFrenchRegistrationDate',
            'acquisitionDate',
            'currentStatus',
        ];
    }

    public static function rules(): array
    {
        $energyValues = array_map(static fn (EnergySource $e): string => $e->value, EnergySource::cases());
        $pollutantValues = array_map(static fn (PollutantCategory $p): string => $p->value, PollutantCategory::cases());
        $availableYears = config('floty.fiscal.available_years', []);
        $yearRule = $availableYears === []
            ? ['nullable', 'integer', 'min:1900', 'max:2100']
            : ['nullable', 'integer', 'in:'.implode(',', $availableYears)];

        return array_merge(parent::rules(), [
            'includeExited' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', 'in:active,maintenance,sold,destroyed,other'],
            'energySource' => ['nullable', 'string', 'in:'.implode(',', $energyValues)],
            'pollutantCategory' => ['nullable', 'string', 'in:'.implode(',', $pollutantValues)],
            'handicapAccess' => ['nullable', 'boolean'],
            'firstRegistrationYearMin' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'firstRegistrationYearMax' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'year' => $yearRule,
        ]);
    }
}
