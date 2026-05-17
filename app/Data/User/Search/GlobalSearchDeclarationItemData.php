<?php

declare(strict_types=1);

namespace App\Data\User\Search;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Item déclaration fiscale retourné par la recherche globale ⌘K (V1.1).
 *
 * **Conditions d'apparition** · la query doit contenir une année
 * (regex `\b(20\d{2})\b`) ET au moins un token entreprise · sinon le
 * groupe n'est pas calculé (pas d'item retourné).
 *
 * Pour chaque couple (entreprise, année), on retourne **la dernière
 * version active** (`is_obsolete = false`) · il n'y a donc qu'un seul
 * résultat par combo, ce qui rend chaque item directement actionnable
 * (un seul résultat = résultat forcément pertinent).
 *
 *  - `label` · « ACME · Déclaration 2025 »
 *  - `sublabel` · statut humanisé (« Générée le 12/03/2026 » / « Brouillon »)
 *  - `href` · URL absolue vers la fiche déclaration
 */
#[TypeScript]
final class GlobalSearchDeclarationItemData extends Data
{
    public function __construct(
        public int $id,
        public string $label,
        public ?string $sublabel,
        public string $href,
    ) {}
}
