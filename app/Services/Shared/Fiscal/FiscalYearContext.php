<?php

declare(strict_types=1);

namespace App\Services\Shared\Fiscal;

use App\Fiscal\Resolver\FiscalYearResolver;
use Illuminate\Contracts\Config\Repository;

/**
 * Petit contexte de référence pour les propriétés calendaires d'une
 * année fiscale (jours dans l'année, années disponibles, support).
 *
 * **Note V1.8** : ce contexte ne porte plus la notion d'« année
 * courante ». L'année active côté utilisateur est désormais résolue
 * par {@see FiscalYearResolver} (lecture session).
 * Cette classe reste utilisée pour les opérations purement calendaires
 * (`daysInYear`) et pour valider qu'une année est supportée par
 * l'installation.
 *
 * **Stateless / immuable** : pas de propriété mutable, partageable en
 * singleton via le container Laravel sans précaution.
 *
 * **Pas de couplage Laravel `app()`** : on lit la config par injection
 * (`Repository`) pour rester testable hors framework.
 */
final class FiscalYearContext
{
    public function __construct(
        private readonly Repository $config,
    ) {}

    /**
     * @return list<int>
     */
    public function availableYears(): array
    {
        return array_values(array_map(
            'intval',
            (array) $this->config->get('floty.fiscal.available_years', []),
        ));
    }

    /**
     * Nombre de jours dans une année grégorienne :
     * 366 si bissextile (divisible par 4 mais pas par 100, ou divisible
     * par 400), 365 sinon.
     *
     * Source unique pour tous les prorata fiscaux côté backend.
     */
    public function daysInYear(int $year): int
    {
        return $this->isLeapYear($year) ? 366 : 365;
    }

    /**
     * Une année supportée par la configuration ?
     */
    public function isSupported(int $year): bool
    {
        return in_array($year, $this->availableYears(), true);
    }

    private function isLeapYear(int $year): bool
    {
        return ($year % 4 === 0 && $year % 100 !== 0) || $year % 400 === 0;
    }
}
