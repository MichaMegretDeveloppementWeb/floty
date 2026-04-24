<?php

namespace App\Enums\Fiscal;

/**
 * Les cinq sous-types fonctionnels d'une règle fiscale Floty (ADR-0006 § 1).
 *
 * Chaque règle du catalogue (R-{year}-{nnn}) implémente exactement un de
 * ces sous-types, qui détermine son rôle dans le pipeline (ADR-0006 § 2).
 *
 * | Sous-type         | Rôle                                                                 |
 * |-------------------|----------------------------------------------------------------------|
 * | Classification    | Produit une qualification à partir des caractéristiques véhicule.    |
 * | Tariff            | Produit un tarif annuel plein à partir des caractéristiques.         |
 * | Exemption         | Vérifie une condition d'exonération (éventuellement avec cumul état).|
 * | Abatement         | Modifie une caractéristique d'entrée avant la tarification.          |
 * | Transversal       | Opérations transverses (prorata, arrondi, indispos, historisation).  |
 */
enum RuleType: string
{
    case Classification = 'classification';
    case Tariff = 'tariff';
    case Exemption = 'exemption';
    case Abatement = 'abatement';
    case Transversal = 'transversal';
}
