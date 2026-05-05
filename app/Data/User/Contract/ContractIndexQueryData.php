<?php

declare(strict_types=1);

namespace App\Data\User\Contract;

use App\Data\Shared\Listing\IndexQueryData;
use App\Data\Shared\Listing\SortDirection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * DTO d'entrée pour l'Index Contracts server-side (cf. ADR-0020).
 *
 * Filtres spécifiques :
 *  - `vehicleId`, `companyId`, `driverId` : filtres exact match sur FK
 *  - `type: 'lcd'|'lld'|null` : filtre exact sur enum
 *  - `year` (chantier J) : sélecteur **mode année** — exercice complet,
 *    mutuellement exclusif avec `periodStart`/`periodEnd` côté front
 *    (le toggle UI choisit l'un OU l'autre). Côté backend, si `year`
 *    présent on dérive `periodStart=YYYY-01-01, periodEnd=YYYY-12-31`
 *    avant filtrage SQL.
 *  - `periodStart` + `periodEnd` (Y-m-d) : filtre **chevauchement** —
 *    le contrat doit chevaucher la fenêtre `[periodStart, periodEnd]`
 *    (start_date <= periodEnd ET end_date >= periodStart).
 *
 * Whitelist sortKey : `vehicle | company | startDate | endDate |
 * duration | type`. Toutes traduisibles en SQL pure (DATEDIFF pour
 * `duration`, JOIN pour `vehicle`/`company`).
 */
#[TypeScript]
final class ContractIndexQueryData extends IndexQueryData
{
    public function __construct(
        public ?int $vehicleId = null,
        public ?int $companyId = null,
        public ?int $driverId = null,
        public ?string $type = null,
        public ?int $year = null,
        public ?string $periodStart = null,
        public ?string $periodEnd = null,
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
        return ['vehicle', 'company', 'startDate', 'endDate', 'duration', 'type'];
    }

    public static function rules(): array
    {
        $availableYears = config('floty.fiscal.available_years', []);
        $yearRule = $availableYears === []
            ? ['nullable', 'integer', 'min:1900', 'max:2100']
            : ['nullable', 'integer', 'in:'.implode(',', $availableYears)];

        return array_merge(parent::rules(), [
            'vehicleId' => ['nullable', 'integer', 'exists:vehicles,id'],
            'companyId' => ['nullable', 'integer', 'exists:companies,id'],
            'driverId' => ['nullable', 'integer', 'exists:drivers,id'],
            'type' => ['nullable', 'string', 'in:lcd,lld'],
            'year' => $yearRule,
            'periodStart' => ['nullable', 'date_format:Y-m-d'],
            'periodEnd' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:periodStart'],
        ]);
    }

    /**
     * Période effective : si `year` présent, dérive l'exercice complet ;
     * sinon retourne `periodStart`/`periodEnd` tels quels. Utilisé par
     * le service pour appliquer le filtre SQL.
     *
     * @return array{periodStart: ?string, periodEnd: ?string}
     */
    public function effectivePeriod(): array
    {
        if ($this->year !== null) {
            return [
                'periodStart' => sprintf('%d-01-01', $this->year),
                'periodEnd' => sprintf('%d-12-31', $this->year),
            ];
        }

        return [
            'periodStart' => $this->periodStart,
            'periodEnd' => $this->periodEnd,
        ];
    }
}
