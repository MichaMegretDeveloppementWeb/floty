<?php

declare(strict_types=1);

namespace App\Data\User\Search;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Item entreprise retourné par la recherche globale ⌘K (V1.1).
 *
 *  - `label` · nom légal (« ACME SARL »)
 *  - `sublabel` · SIREN si renseigné (« SIREN 123 456 789 »), sinon
 *    ville ou null
 *  - `href` · URL absolue vers la fiche entreprise
 */
#[TypeScript]
final class GlobalSearchCompanyItemData extends Data
{
    public function __construct(
        public int $id,
        public string $label,
        public ?string $sublabel,
        public string $href,
    ) {}
}
