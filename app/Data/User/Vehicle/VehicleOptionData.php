<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Option véhicule pour les `<SelectInput>` des formulaires
 * (Attribution rapide, Drawer, etc.).
 *
 * Les taxes pleines sont fournies pour les années couvertes par le
 * scope dynamique (`AvailableYearsResolver`). Le formulaire de contrat
 * affiche la taxe pleine de l'année correspondant à la `start_date`
 * saisie ; fallback année courante quand la date n'est pas encore
 * renseignée.
 *
 * @phpstan-type FullYearTaxByYear array<int, float>
 */
#[TypeScript]
final class VehicleOptionData extends Data
{
    /**
     * @param  array<int, float>  $fullYearTaxByYear  Map year → taxe pleine €
     */
    public function __construct(
        public int $id,
        public string $licensePlate,
        public string $label,
        public bool $isExited,
        public ?string $exitDate,
        public array $fullYearTaxByYear = [],
    ) {}
}
