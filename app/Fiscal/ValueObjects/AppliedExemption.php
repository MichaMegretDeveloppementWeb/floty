<?php

declare(strict_types=1);

namespace App\Fiscal\ValueObjects;

/**
 * Couple `(reason, ruleCode)` exposé dans le résultat du pipeline pour
 * affichage utilisateur (panneau "Exonérations applicables").
 *
 * Permet de tracer chaque motif textuel jusqu'à la règle métier qui l'a
 * produit, pour ouvrir la fiche R-2024-XXX correspondante depuis l'UI.
 */
final readonly class AppliedExemption
{
    public function __construct(
        public string $reason,
        public string $ruleCode,
    ) {}
}
