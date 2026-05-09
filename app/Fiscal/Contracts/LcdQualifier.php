<?php

declare(strict_types=1);

namespace App\Fiscal\Contracts;

use App\Models\Contract;

/**
 * Contrat de qualification LCD (Location de Courte Durée) au sens
 * fiscal CIBS (cf. ADR-0014 + BOFiP § 180-190).
 *
 * **Pourquoi cette interface** : la règle R-2024-021 expose la
 * qualification LCD via `isShortTermRental(Contract): bool`. Plusieurs
 * services consomment cette qualification ({@see App\Fiscal\Year2024\Exemption\R2024_008_ReductiveUnavailability},
 * {@see App\Services\Fiscal\RiskDetection\LcdContractFilter}). Pour
 * permettre la **substitution runtime** de la règle par un decorator
 * (chantier Phase 11 D5.1, mécanisme de skip-rule porté par les
 * décisions humaines de revue), les consommateurs dépendent de cette
 * interface plutôt que de la classe concrète.
 *
 * **Implémenteurs** :
 * - {@see App\Fiscal\Year2024\Exemption\R2024_021_ShortTermRental} :
 *   implémentation canonique, lecture stricte BOFiP par contrat.
 * - {@see App\Fiscal\Year2024\Exemption\R2024_021_WithOptOuts} :
 *   decorator runtime qui force `false` pour les contracts marqués
 *   opt-out (décisions « Requalified » du workflow de revue).
 */
interface LcdQualifier
{
    /**
     * Vrai ssi le contrat est qualifié LCD au sens fiscal.
     */
    public function isShortTermRental(Contract $contract): bool;
}
