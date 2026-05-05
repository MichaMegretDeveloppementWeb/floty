<?php

declare(strict_types=1);

namespace App\Services\Fiscal;

use App\Contracts\Repositories\User\Contract\ContractReadRepositoryInterface;
use App\Providers\AppServiceProvider;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Résout dynamiquement les bornes des années sélectionnables côté UI.
 *
 * **Doctrine temporelle (chantier η refondu, 2026-05-05)** : Floty ne
 * contraint plus en dur la liste des années via `config()`. Les bornes
 * sont calculées depuis les **contrats** :
 *
 *   - `minYear()` = `MIN(YEAR(start_date))` sur les contrats non
 *     soft-deletés ; si la table est vide → `currentYear()`.
 *   - `maxYear()` = `MAX(currentYear(), MAX(YEAR(start_date)))` — capture
 *     les contrats anticipés (saisis pour une année future).
 *   - `availableYears()` = range continu `[minYear, …, maxYear]`.
 *
 * **Conséquence métier** : si Renaud rentre un contrat 2023, l'année 2023
 * apparaît automatiquement dans tous les sélecteurs (sans intervention
 * de configuration). Si tous les contrats 2024 sont supprimés, 2024
 * disparaît à son tour. Le sélecteur reflète l'état réel des données.
 *
 * **Performance** : les bornes sont mises en cache sous la clé
 * {@see self::CACHE_KEY} avec TTL infini. L'invalidation est portée par
 * le `ContractObserver` (sous-chantier 0.2) qui appelle
 * {@see forgetCache()} sur tout `created`/`updated`/`deleted`/`restored`
 * d'un `Contract`. La query SQL sous-jacente est résolue en lecture
 * d'index seul (cf. `contracts_*` indexes sur `start_date`).
 *
 * **Singleton** : enregistré dans {@see AppServiceProvider}.
 * Garantit qu'au sein d'une même requête HTTP, plusieurs appels à
 * `availableYears()` partagent le même cache mémoire process.
 *
 * **Scope** : global (pas par entité). Décision HD4 du chantier η —
 * cohérence UX : tous les sélecteurs de l'app affichent la même liste
 * d'années, peu importe la fiche consultée.
 */
final class AvailableYearsResolver
{
    /**
     * Clé de cache des bornes.
     *
     * Sérialise un array de la forme `['min' => ?int, 'max' => ?int]`.
     * Invalidée par {@see ContractObserver} sur toute mutation de Contract.
     */
    public const CACHE_KEY = 'floty:contracts:year_bounds';

    public function __construct(
        private readonly ContractReadRepositoryInterface $contracts,
        private readonly CacheRepository $cache,
    ) {}

    /**
     * Année calendaire réelle. Pas de cache (lecture horloge système).
     */
    public function currentYear(): int
    {
        return (int) CarbonImmutable::now()->year;
    }

    /**
     * Année min globale = MIN(YEAR(start_date)) sur contrats non
     * soft-deletés. Fallback `currentYear()` si la table est vide.
     */
    public function minYear(): int
    {
        $bounds = $this->bounds();
        $current = $this->currentYear();

        return $bounds['min'] !== null ? min($bounds['min'], $current) : $current;
    }

    /**
     * Année max = MAX(currentYear, MAX(YEAR(start_date))). Capture les
     * contrats futurs saisis en avance.
     */
    public function maxYear(): int
    {
        $bounds = $this->bounds();
        $current = $this->currentYear();

        return $bounds['max'] !== null ? max($bounds['max'], $current) : $current;
    }

    /**
     * Range continu [minYear, …, maxYear] — toutes les années
     * sélectionnables côté UI.
     *
     * @return list<int>
     */
    public function availableYears(): array
    {
        $min = $this->minYear();
        $max = $this->maxYear();

        return range($min, $max);
    }

    /**
     * Invalide le cache des bornes. Appelée par le `ContractObserver`
     * (chantier 0.2) sur toute mutation de Contract. Exposée publique
     * pour permettre une invalidation manuelle (commands artisan,
     * tests, scénarios exceptionnels).
     */
    public function forgetCache(): void
    {
        $this->cache->forget(self::CACHE_KEY);
    }

    /**
     * Bornes brutes depuis le repo (avec cache). Une seule query SQL
     * indexée par cache miss.
     *
     * @return array{min: int|null, max: int|null}
     */
    private function bounds(): array
    {
        /** @var array{min: int|null, max: int|null} */
        return $this->cache->rememberForever(
            self::CACHE_KEY,
            fn (): array => $this->contracts->yearBounds(),
        );
    }
}
