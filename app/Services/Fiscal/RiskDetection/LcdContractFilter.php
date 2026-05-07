<?php

declare(strict_types=1);

namespace App\Services\Fiscal\RiskDetection;

use App\Fiscal\Year2024\Exemption\R2024_021_ShortTermRental;
use App\Models\Contract;

/**
 * Filtre la qualification LCD (Location de Courte Durée) sur une
 * collection de contrats (Phase 11 D2).
 *
 * Délègue strictement à la règle fiscale souveraine
 * {@see R2024_021_ShortTermRental::isShortTermRental} : la
 * qualification LCD au sens fiscal est portée par la règle (durée
 * ≤ 30 jours OU mois civil entier, ADR-0014 + BOFiP § 180-190),
 * pas par le libellé `contract_type` persisté en BDD qui n'est
 * qu'indicatif (mémoire `feedback_fiscal_rules_authority`).
 *
 * Service séparé du moteur de détection pour permettre le mocking
 * en test isolé du `RiskDetectionService` sans avoir à instancier
 * la règle complète.
 */
final readonly class LcdContractFilter
{
    public function __construct(
        private R2024_021_ShortTermRental $rule,
    ) {}

    /**
     * Vraie ssi le contrat est qualifié LCD au sens fiscal.
     */
    public function isLcd(Contract $contract): bool
    {
        return $this->rule->isShortTermRental($contract);
    }
}
