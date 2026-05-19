<?php

declare(strict_types=1);

namespace App\Data\User\FiscalDeclaration;

use App\Data\Shared\Listing\PaginationMetaData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Return wrapper for the server-side declarations index (ADR-0020).
 */
#[TypeScript]
final class PaginatedDeclarationListData extends Data
{
    /**
     * @param  array<int, DeclarationListItemData>  $data
     */
    public function __construct(
        public array $data,
        public PaginationMetaData $meta,
    ) {}
}
