<?php

declare(strict_types=1);

namespace App\Strategies\VehicleRegistryLookup\Support;

/**
 * Normalisation des plaques d'immatriculation pour les appels aux
 * providers et les comparaisons de fixtures.
 *
 * Règles :
 *   - uppercase
 *   - suppression de tous les caractères non alphanumériques (espaces,
 *     tirets, points)
 *
 * `AB-123-CD`, `ab 123 cd`, `AB123CD` → `AB123CD`.
 *
 * Aucune validation de format ici · la responsabilité validation est
 * portée par le FormRequest du controller (regex SIV).
 */
final class LicensePlateNormalizer
{
    public static function normalize(string $licensePlate): string
    {
        $stripped = preg_replace('/[^A-Za-z0-9]+/', '', $licensePlate) ?? '';

        return strtoupper($stripped);
    }
}
