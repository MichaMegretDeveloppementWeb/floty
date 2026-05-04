<?php

declare(strict_types=1);

namespace App\Data\User\Contract;

use App\Data\Shared\Listing\PaginationMetaData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Wrapper de retour pour l'Index Contracts server-side (cf. ADR-0020).
 */
#[TypeScript]
final class PaginatedContractListData extends Data
{
    /**
     * @param  array<int, ContractListItemData>  $data
     */
    public function __construct(
        public array $data,
        public PaginationMetaData $meta,
    ) {}
}
