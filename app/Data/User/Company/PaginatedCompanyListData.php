<?php

declare(strict_types=1);

namespace App\Data\User\Company;

use App\Data\Shared\Listing\PaginationMetaData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Wrapper de retour pour l'Index Companies server-side (cf. ADR-0020).
 *
 * `data` contient les CompanyListItemData de la page courante (avec
 * leurs aggregates fiscaux `daysUsed` + `annualTaxDue` calculés
 * uniquement pour les entreprises affichées, pas tout le dataset).
 */
#[TypeScript]
final class PaginatedCompanyListData extends Data
{
    /**
     * @param  array<int, CompanyListItemData>  $data
     */
    public function __construct(
        public array $data,
        public PaginationMetaData $meta,
    ) {}
}
