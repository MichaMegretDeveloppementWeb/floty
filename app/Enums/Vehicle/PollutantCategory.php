<?php

namespace App\Enums\Vehicle;

/**
 * Catégorie d'émissions de polluants (CIBS art. L. 421-134).
 *
 * Calculée par R-2024-013 à partir de {@see EnergySource},
 * {@see UnderlyingCombustionEngineType} et {@see EuroStandard}.
 *
 * - `E`              : Diesel hybrides exclus, strictement électriques/hydrogène
 * - `Category1`      : moteurs à allumage commandé (essence/GPL/GNV/E85 ou
 *                      hybrides à sous-jacent essence) Euro 5/6
 * - `MostPolluting`  : tous les autres (Diesel, essence pré-Euro 5, sans norme)
 */
enum PollutantCategory: string
{
    case E = 'e';
    case Category1 = 'category_1';
    case MostPolluting = 'most_polluting';
}
