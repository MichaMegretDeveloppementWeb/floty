<?php

declare(strict_types=1);

namespace App\Fiscal\Contracts\Concerns;

use Carbon\CarbonImmutable;

/**
 * Trait à appliquer sur toute règle fiscale dont l'applicabilité couvre
 * l'année fiscale entière (cas par défaut, applicable à la grande
 * majorité des règles 2024).
 *
 * La classe consommatrice doit fournir {@see fiscalYear()} ; les bornes
 * temporelles sont alors dérivées automatiquement :
 *   - `applicabilityStart()` retourne `{year}-01-01` 00:00:00
 *   - `applicabilityEnd()` retourne `{year}-12-31` 23:59:59
 *
 * Pour une règle partielle (apparition ou disparition en cours d'année),
 * ne PAS utiliser ce trait : implémenter directement les méthodes
 * `applicabilityStart()` / `applicabilityEnd()` dans la classe.
 *
 * **Concern orthogonal** : le défaut « actif » de `isActive()` vit dans
 * {@see RuleActiveByDefaultTrait} (séparé pour ne pas coupler temporalité
 * et état actif).
 */
trait AnnualRuleTrait
{
    abstract public function fiscalYear(): int;

    public function applicabilityStart(): CarbonImmutable
    {
        return CarbonImmutable::create($this->fiscalYear(), 1, 1, 0, 0, 0);
    }

    public function applicabilityEnd(): ?CarbonImmutable
    {
        return CarbonImmutable::create($this->fiscalYear(), 12, 31, 23, 59, 59);
    }
}
