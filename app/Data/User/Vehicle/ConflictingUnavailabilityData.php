<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use App\Enums\Unavailability\UnavailabilityType;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Description minimale d'une indisponibilité active qui déborde une
 * date de sortie de flotte proposée — affichée dans la modale Sortie
 * pour permettre à l'utilisateur d'aller la résoudre.
 */
#[TypeScript]
final class ConflictingUnavailabilityData extends Data
{
    public function __construct(
        public int $id,
        public UnavailabilityType $type,
        public string $startDate,
        public string $endDate,
    ) {}
}
