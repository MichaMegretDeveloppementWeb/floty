<?php

declare(strict_types=1);

namespace App\Data\User\Company;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Une ligne du top des véhicules les plus attribués à une entreprise
 * sur un exercice — alimente la liste « Top véhicules » de la section
 * Activité de la fiche entreprise (chantier K L2).
 *
 * Le `percentage` est calculé sur la **somme des jours-véhicules** de
 * l'entreprise pour l'année (pas sur les jours calendaires) — ce qui
 * permet la lecture comparative « ce véhicule représente X % de
 * l'activité de l'entreprise ».
 */
#[TypeScript]
final class CompanyTopVehicleData extends Data
{
    public function __construct(
        public int $vehicleId,
        public string $licensePlate,
        public string $brand,
        public string $model,
        public int $daysUsed,
        /** Pourcentage [0..100] arrondi à 1 décimale du total annuel jours-véhicules de l'entreprise. */
        public float $percentage,
    ) {}
}
