<?php

declare(strict_types=1);

namespace App\Services\Shared\Fiscal;

use Illuminate\Contracts\Config\Repository;

/**
 * Petit contexte de référence pour l'année fiscale active et les
 * propriétés calendaires associées (jours dans l'année, années
 * disponibles, etc.).
 *
 * **Pourquoi un service** : centraliser la lecture de
 * `config('floty.fiscal.*')` et les calculs bissextiles évite que
 * chaque controller / service / job ne re-fasse la conversion à la
 * main. Le seul endroit qui hardcodait `366` (FiscalCalculator) ou
 * comparait `$year === 2024` peut maintenant interroger ce contexte.
 *
 * **Stateless / immuable** : pas de propriété mutable, peut être
 * partagé en singleton via le container Laravel sans précaution.
 *
 * **Pas de couplage Laravel `app()`** : on lit la config par
 * injection (`Repository`) pour rester testable hors framework.
 */
final class FiscalYearContext
{
    public function __construct(
        private readonly Repository $config,
    ) {}

    /**
     * Année fiscale courante exposée par `config/floty.php`.
     */
    public function currentYear(): int
    {
        return (int) $this->config->get('floty.fiscal.current_year');
    }

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
     * Raccourci pratique : nombre de jours dans l'année fiscale courante.
     */
    public function daysInCurrentYear(): int
    {
        return $this->daysInYear($this->currentYear());
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
