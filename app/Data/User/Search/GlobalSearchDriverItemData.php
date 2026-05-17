<?php

declare(strict_types=1);

namespace App\Data\User\Search;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Item conducteur retourné par la recherche globale ⌘K (V1.1).
 *
 *  - `label` · nom complet (« Jean Dupont »)
 *  - `sublabel` · entreprises actives concaténées (« ACME, BetaCorp »)
 *    ou null si aucune affiliation active
 *  - `href` · URL absolue vers la fiche conducteur
 */
#[TypeScript]
final class GlobalSearchDriverItemData extends Data
{
    public function __construct(
        public int $id,
        public string $label,
        public ?string $sublabel,
        public string $href,
    ) {}
}
