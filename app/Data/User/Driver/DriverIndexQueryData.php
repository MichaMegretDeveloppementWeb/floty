<?php

declare(strict_types=1);

namespace App\Data\User\Driver;

use App\Data\Shared\Listing\IndexQueryData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * DTO d'entrée pour l'Index Drivers server-side (cf. ADR-0020).
 *
 * Pas de filtre spécifique au domaine (les drivers n'ont pas d'attributs
 * filtrables en V1.1 hors la search par fullName, gérée par
 * `IndexQueryData::$search`).
 *
 * Whitelist sortKey : `fullName | contractsCount | activeCompaniesCount`.
 */
#[TypeScript]
final class DriverIndexQueryData extends IndexQueryData
{
    public static function allowedSortKeys(): array
    {
        return ['fullName', 'contractsCount', 'activeCompaniesCount'];
    }
}
