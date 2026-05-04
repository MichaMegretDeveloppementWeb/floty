<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use App\Data\Shared\Listing\PaginationMetaData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Wrapper de retour pour l'Index Vehicles server-side (cf. ADR-0020).
 *
 * `data` contient les VehicleListItemData de la page courante (avec
 * `fullYearTax` + `dailyTaxRate` calculés uniquement pour les véhicules
 * affichés, pas tout le dataset).
 */
#[TypeScript]
final class PaginatedVehicleListData extends Data
{
    /**
     * @param  array<int, VehicleListItemData>  $data
     */
    public function __construct(
        public array $data,
        public PaginationMetaData $meta,
    ) {}
}
