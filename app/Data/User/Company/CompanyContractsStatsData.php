<?php

declare(strict_types=1);

namespace App\Data\User\Company;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Stats contextuelles affichées sous le titre de l'onglet Contrats
 * de la fiche Company Show (chantier N.1.fixes). Reflètent le filtre
 * période actif — bougent avec le filtre, aident l'utilisateur à se
 * situer dans l'historique de l'entreprise.
 *
 * `totalDays` est calculé en intersection avec la fenêtre filtrée :
 * un contrat 01/01–31/12 affiché dans un filtre Q3 ne compte que les
 * 92 jours de juillet–septembre, pas 365.
 */
#[TypeScript]
final class CompanyContractsStatsData extends Data
{
    public function __construct(
        public int $totalDays,
        public int $lcdCount,
        public int $lldCount,
    ) {}
}
