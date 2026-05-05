<?php

declare(strict_types=1);

namespace App\Services\Shared\Fiscal;

use App\Fiscal\Registry\FiscalRuleRegistry;

/**
 * Contexte de référence pour les propriétés calendaires d'une année
 * fiscale (jours dans l'année) et la vérification qu'une année est
 * couverte par le moteur fiscal (registry des règles codées).
 *
 * **Doctrine "données métier ⊥ règles fiscales"** (chantier η Phase 5) :
 * la source d'autorité est désormais le {@see FiscalRuleRegistry} (les
 * règles que l'app sait calculer), pas une config statique parallèle.
 * La config `floty.fiscal.available_years` a été supprimée.
 *
 * **Stateless / immuable** : pas de propriété mutable, partageable en
 * singleton via le container Laravel sans précaution.
 */
final class FiscalYearContext
{
    public function __construct(
        private readonly FiscalRuleRegistry $registry,
    ) {}

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
     * Une année supportée par le moteur fiscal (au moins une règle
     * enregistrée pour cette année dans le registry) ?
     */
    public function isSupported(int $year): bool
    {
        return in_array($year, $this->registry->registeredYears(), true);
    }

    private function isLeapYear(int $year): bool
    {
        return ($year % 4 === 0 && $year % 100 !== 0) || $year % 400 === 0;
    }
}
