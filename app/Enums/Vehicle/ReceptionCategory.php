<?php

declare(strict_types=1);

namespace App\Enums\Vehicle;

/**
 * Catégorie européenne de réception du véhicule (rubrique J.1 de la carte grise).
 *
 * Codes administratifs universels conservés tels quels (cf. E1).
 *
 * - M1 : véhicule destiné au transport de personnes comptant, outre le siège
 *        du conducteur, huit places assises au maximum (voiture particulière).
 * - N1 : véhicule destiné au transport de marchandises ayant un poids maximal
 *        ≤ 3,5 tonnes (camionnette).
 */
enum ReceptionCategory: string
{
    case M1 = 'M1';
    case N1 = 'N1';

    public function label(): string
    {
        return match ($this) {
            self::M1 => 'M1 — Voiture particulière (≤ 8 places)',
            self::N1 => 'N1 — Camionnette (PTAC ≤ 3,5 t)',
        };
    }
}
