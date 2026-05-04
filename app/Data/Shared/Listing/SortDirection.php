<?php

declare(strict_types=1);

namespace App\Data\Shared\Listing;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Direction de tri d'une table d'index server-side (cf. ADR-0020).
 *
 * Exposée en TypeScript via `App.Enums.SortDirection`.
 */
#[TypeScript]
enum SortDirection: string
{
    case Asc = 'asc';
    case Desc = 'desc';
}
