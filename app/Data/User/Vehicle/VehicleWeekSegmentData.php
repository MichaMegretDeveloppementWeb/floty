<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use App\Enums\Company\CompanyColor;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Un segment empilé de la cellule semaine de la timeline d'utilisation
 * d'un véhicule (1 segment = 1 entreprise présente sur cette semaine).
 *
 * Hauteur du segment côté front = `days / 7 × hauteurCellule`.
 */
#[TypeScript]
final class VehicleWeekSegmentData extends Data
{
    public function __construct(
        public int $companyId,
        public string $shortCode,
        public CompanyColor $color,
        public int $days,
    ) {}
}
