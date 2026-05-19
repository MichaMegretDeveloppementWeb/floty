<?php

declare(strict_types=1);

namespace App\Data\Shared\Listing;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Sort direction for a server-side index table (ADR-0020).
 */
#[TypeScript]
enum SortDirection: string
{
    case Asc = 'asc';
    case Desc = 'desc';
}
