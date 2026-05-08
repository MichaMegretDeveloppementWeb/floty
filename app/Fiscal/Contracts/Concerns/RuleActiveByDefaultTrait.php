<?php

declare(strict_types=1);

namespace App\Fiscal\Contracts\Concerns;

/**
 * Trait minimal qui fournit le défaut « actif » pour `isActive()`.
 *
 * **Pourquoi ce trait isolé** : la grande majorité des règles fiscales
 * sont actives par défaut. Auparavant, ce défaut vivait dans
 * {@see AnnualRuleTrait}, ce qui mélangeait deux concerns orthogonaux
 * (temporalité annuelle vs état actif). Une règle partielle peut être
 * active ; une règle annuelle peut être inactive (R-2024-018 OIG,
 * R-2024-019 IndividualBusiness en V1).
 *
 * **Usage** : adopter ce trait sur toute règle pipeline qui doit être
 * active par défaut. Pour désactiver, ne PAS utiliser le trait et
 * implémenter directement `isActive(): bool { return false; }`.
 */
trait RuleActiveByDefaultTrait
{
    public function isActive(): bool
    {
        return true;
    }
}
