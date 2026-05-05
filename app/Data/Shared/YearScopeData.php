<?php

declare(strict_types=1);

namespace App\Data\Shared;

use App\Services\Fiscal\AvailableYearsResolver;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Scope d'années sélectionnables exposé en **prop par page** Inertia
 * (pas en shared props) — fondation de la doctrine temporelle
 * (chantier η Phase 0.3).
 *
 * **Pourquoi un DTO par page plutôt qu'un shared global** : décision HD3
 * du chantier η — pas de carry-over d'année entre pages, chaque section
 * choisit son défaut. Centraliser en shared encouragerait un couplage
 * implicite (« la page A modifie l'année, la page B en hérite ») qu'on
 * veut éviter.
 *
 * **Co-existence avec `FiscalSharedData`** : `FiscalSharedData` reste
 * exposé en shared props pour l'instant (alimenté par
 * `config('floty.fiscal.available_years')` — ancien chemin caduc). Le
 * cleanup Phase 5 supprimera ce shared global au profit de cette prop
 * locale.
 *
 * **Champs** :
 *   - `currentYear` : année calendaire réelle (2026 aujourd'hui). Sert
 *     de référence pour les KPIs « Présent » et le défaut des sélecteurs.
 *   - `minYear` : année min globale calculée depuis les contrats actifs.
 *   - `availableYears` : range continu `[minYear, …, max]`. Le `max`
 *     est implicite (= `last(availableYears)`), pas dupliqué pour rester
 *     DRY côté payload.
 *
 * **Source unique** : {@see AvailableYearsResolver}. La factory
 * {@see fromResolver()} est le seul chemin de construction recommandé en
 * production (le constructor reste public pour les tests et les cas
 * exceptionnels d'injection de valeurs custom).
 */
#[TypeScript]
final class YearScopeData extends Data
{
    /**
     * @param  list<int>  $availableYears
     */
    public function __construct(
        public int $currentYear,
        public int $minYear,
        public array $availableYears,
    ) {}

    /**
     * Construit le DTO depuis le service singleton fiscal — chemin
     * recommandé en production. Les 3 méthodes du resolver sont appelées
     * exactement une fois (cache process-level via le singleton).
     */
    public static function fromResolver(AvailableYearsResolver $resolver): self
    {
        return new self(
            currentYear: $resolver->currentYear(),
            minYear: $resolver->minYear(),
            availableYears: $resolver->availableYears(),
        );
    }
}
