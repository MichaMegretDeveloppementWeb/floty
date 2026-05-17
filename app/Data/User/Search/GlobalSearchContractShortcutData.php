<?php

declare(strict_types=1);

namespace App\Data\User\Search;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * **Raccourci** vers la table Contrats filtrée par couple
 * (véhicule × entreprise), proposé par la recherche globale ⌘K
 * UNIQUEMENT quand la query croise au moins un véhicule et au moins
 * une entreprise (≥ 2 tokens). Pas un item contrat individuel · l'idée
 * est d'amener l'utilisateur vers la liste filtrée plutôt que de lui
 * proposer N contrats à plat.
 *
 *  - `label` · « Renault Clio AB-123-CD · chez ACME »
 *  - `sublabel` · « 3 contrats »
 *  - `href` · `/app/contracts?vehicleId=X&companyId=Y`
 */
#[TypeScript]
final class GlobalSearchContractShortcutData extends Data
{
    public function __construct(
        public int $vehicleId,
        public int $companyId,
        public string $label,
        public string $sublabel,
        public int $count,
        public string $href,
    ) {}
}
