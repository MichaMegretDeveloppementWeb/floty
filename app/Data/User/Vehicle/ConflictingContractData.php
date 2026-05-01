<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Description minimale d'un contrat actif qui déborde une date de
 * sortie de flotte proposée — affichée dans la modale Sortie pour
 * permettre à l'utilisateur d'aller le résoudre (lien direct).
 */
#[TypeScript]
final class ConflictingContractData extends Data
{
    public function __construct(
        public int $id,
        public string $companyShortCode,
        public string $startDate,
        public string $endDate,
    ) {}
}
