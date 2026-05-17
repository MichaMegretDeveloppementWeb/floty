<?php

declare(strict_types=1);

namespace App\Data\User\Search;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Item véhicule retourné par la recherche globale ⌘K (V1.1).
 *
 *  - `label` · « Renault Clio · AB-123-CD » (marque + modèle + plaque)
 *  - `sublabel` · état du véhicule (« Actif » / « Sorti le 12/03/2025 »)
 *  - `href` · URL absolue vers la fiche véhicule
 */
#[TypeScript]
final class GlobalSearchVehicleItemData extends Data
{
    public function __construct(
        public int $id,
        public string $label,
        public ?string $sublabel,
        public string $href,
    ) {}
}
