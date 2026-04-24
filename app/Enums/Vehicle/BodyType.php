<?php

namespace App\Enums\Vehicle;

/**
 * Carrosserie du véhicule (rubrique J.2 de la carte grise).
 * Codes administratifs français préservés (cf. E1).
 *
 * - CI   : Conduite Intérieure (berline, monospace)
 * - BB   : Break
 * - CTTE : Camionnette
 * - BE   : Pick-up
 * - HB   : Handicap / véhicule aménagé
 */
enum BodyType: string
{
    case InteriorDriving = 'CI';
    case StationWagon = 'BB';
    case LightTruck = 'CTTE';
    case Pickup = 'BE';
    case Handicap = 'HB';
}
