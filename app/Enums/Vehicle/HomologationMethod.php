<?php

namespace App\Enums\Vehicle;

/**
 * Méthode d'homologation utilisée pour déterminer le barème CO₂ applicable
 * (cf. CIBS L. 421-120/121/122, implémenté par R-2024-005/006).
 *
 * Codes administratifs conservés tels quels (cf. E1).
 *
 * - WLTP : Worldwide harmonised Light vehicles Test Procedure
 *          (obligatoire pour 1ère immat. France ≥ 2020-03-01)
 * - NEDC : New European Driving Cycle (ancienne méthode)
 * - PA   : Puissance Administrative (fallback si CO₂ manquant ou
 *          véhicule pre-2004)
 */
enum HomologationMethod: string
{
    case Wltp = 'WLTP';
    case Nedc = 'NEDC';
    case Pa = 'PA';
}
