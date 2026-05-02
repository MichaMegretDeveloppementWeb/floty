<?php

declare(strict_types=1);

namespace App\Enums\Driver;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Mode de résolution des contrats à venir lors de la sortie d'un
 * conducteur d'une entreprise (workflow Q6 — Phase 06 V1.2).
 *
 * - `Replace` : pour chaque contrat à venir, l'utilisateur choisit un
 *   driver de remplacement (actif sur la période exacte du contrat).
 * - `Detach` : tous les contrats à venir passent à `driver_id = NULL`.
 * - `None` : il n'y a aucun contrat à venir, ou l'utilisateur a
 *   explicitement choisi de ne rien faire (cas dégénéré : `left_at`
 *   tardif après tous les contrats).
 */
#[TypeScript]
enum FutureContractsResolutionMode: string
{
    case Replace = 'replace';
    case Detach = 'detach';
    case None = 'none';
}
