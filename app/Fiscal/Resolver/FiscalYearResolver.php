<?php

declare(strict_types=1);

namespace App\Fiscal\Resolver;

use App\Exceptions\Fiscal\FiscalCalculationException;
use App\Services\Shared\Fiscal\FiscalYearContext;
use Illuminate\Contracts\Session\Session;

/**
 * Résout l'année fiscale active pour la requête HTTP courante.
 *
 * Stratégie :
 *   1. lit `session('fiscal.active_year')` si présent et présent dans
 *      la liste `availableYears` exposée par {@see FiscalYearContext}
 *   2. sinon fallback sur la **première année disponible** de la
 *      configuration (`available_years[0]`)
 *
 * **Pourquoi pas la config** : si l'année active vivait dans `config(...)`,
 * elle serait globale à l'application — un changement basculerait tous
 * les utilisateurs simultanément. La session permet à chaque utilisateur
 * d'avoir son année active indépendamment des autres.
 *
 * **Pas d'endpoint POST en V1** : tant qu'une seule année est
 * configurée, `setActiveYear()` n'est appelé nulle part. Sera exposé
 * via une route dédiée quand 2025 sera ajoutée (phase ultérieure).
 */
final class FiscalYearResolver
{
    public function __construct(
        private readonly Session $session,
        private readonly FiscalYearContext $context,
    ) {}

    public function resolve(): int
    {
        $sessionYear = $this->session->get('fiscal.active_year');
        if (is_int($sessionYear) && $this->context->isSupported($sessionYear)) {
            return $sessionYear;
        }

        $available = $this->context->availableYears();
        if ($available === []) {
            throw FiscalCalculationException::noYearsConfigured();
        }

        return $available[0];
    }

    public function setActiveYear(int $year): void
    {
        if (! $this->context->isSupported($year)) {
            throw FiscalCalculationException::yearNotSupported($year);
        }
        $this->session->put('fiscal.active_year', $year);
    }
}
