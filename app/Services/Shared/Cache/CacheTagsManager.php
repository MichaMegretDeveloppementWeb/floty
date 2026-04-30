<?php

declare(strict_types=1);

namespace App\Services\Shared\Cache;

use App\Exceptions\Cache\CacheTagsException;
use Illuminate\Cache\CacheManager;
use Illuminate\Cache\DatabaseStore;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

/**
 * Gestion des tags de cache émulée au-dessus du driver `database`.
 *
 * Motivation (cf. ADR-0008 + phase 01.10) : Hostinger Business ne fournit
 * ni Redis ni Memcached. Laravel ne supporte nativement les **tags** que
 * sur Redis/Memcached, pas sur le driver `database`. Floty émule donc les
 * tags par une **convention de préfixes hiérarchiques** sur les clés :
 *
 *   vehicle:42:fiscal_characteristics
 *   vehicle:42:current_contracts
 *   vehicle:42:lcd_cumul:acme:2024
 *
 * Invalider « tout ce qui concerne le véhicule 42 » revient alors à
 * supprimer en base tous les enregistrements dont la clé commence par
 * `vehicle:42:`. C'est exactement ce que fait {@see invalidateByPrefix()}.
 *
 * ### Convention d'emploi côté appelant
 *
 * ```php
 * // Écriture — composer la clé via key() pour garantir la cohérence.
 * Cache::remember(
 *     $this->cacheTags->key('vehicle', $vehicleId, 'fiscal_characteristics'),
 *     ttl: 3600,
 *     callback: fn () => $this->repo->loadFiscal($vehicleId),
 * );
 *
 * // Invalidation — tout ce qui « tague » ce véhicule est balayé.
 * $this->cacheTags->invalidateByPrefix(
 *     $this->cacheTags->key('vehicle', $vehicleId)
 * );
 * ```
 *
 * ### Garanties
 *
 * - `invalidateByPrefix('vehicle:42')` supprime la clé exacte `vehicle:42`
 *   ET tous ses descendants `vehicle:42:*`, mais jamais un voisin comme
 *   `vehicle:420:*` (un `:` final est ajouté à la clause LIKE pour garantir
 *   la frontière).
 * - Les métacaractères LIKE (`%`, `_`, `\`) présents dans un préfixe sont
 *   échappés, bonne pratique même si nos conventions n'en contiennent pas.
 * - Le préfixe global du cache store Laravel (`cache.prefix`) est appliqué
 *   automatiquement au niveau SQL.
 *
 * ### Migration future vers Redis
 *
 * Le jour où Floty migre sur un VPS avec Redis, les services consommateurs
 * peuvent ignorer ce manager et utiliser directement `Cache::tags([...])`.
 * Les clés produites par {@see key()} restent compatibles (Redis-safe).
 */
final class CacheTagsManager
{
    private const DEFAULT_STORE = 'database';

    public function __construct(
        private readonly CacheManager $cache,
        private readonly ConfigRepository $config,
    ) {}

    /**
     * Compose une clé de cache à partir de segments joints par `:`.
     * Centralise la convention pour garantir qu'on construit toujours
     * une hiérarchie compatible avec {@see invalidateByPrefix()}.
     *
     * Exemple : `key('vehicle', 42, 'fiscal')` → `"vehicle:42:fiscal"`.
     */
    public function key(string|int ...$parts): string
    {
        if ($parts === []) {
            throw CacheTagsException::keyRequiresAtLeastOneSegment();
        }

        return implode(':', array_map(static fn (string|int $part) => (string) $part, $parts));
    }

    /**
     * Supprime la clé exacte correspondant au préfixe donné ET tous ses
     * descendants. Un éventuel `:` final sur l'argument est normalisé.
     *
     * @return int Nombre de clés supprimées, utile pour les logs et les
     *             assertions de tests.
     */
    public function invalidateByPrefix(string $logicalPrefix): int
    {
        $store = $this->resolveDatabaseStore();
        $table = $this->config->get('cache.stores.'.self::DEFAULT_STORE.'.table', 'cache');

        $normalized = rtrim($logicalPrefix, ':');
        $exactKey = $store->getPrefix().$normalized;
        $descendantsPattern = $this->escapeLikeLiteral($exactKey.':').'%';

        return $store->getConnection()
            ->table($table)
            ->where(function ($query) use ($exactKey, $descendantsPattern): void {
                $query
                    ->where('key', '=', $exactKey)
                    ->orWhere('key', 'like', $descendantsPattern);
            })
            ->delete();
    }

    /**
     * Renvoie le {@see DatabaseStore} sous-jacent ou lève si la store
     * « database » n'est pas résoluble — précaution contre une mauvaise
     * configuration qui sinon invaliderait silencieusement 0 entrée.
     */
    private function resolveDatabaseStore(): DatabaseStore
    {
        $store = $this->cache->store(self::DEFAULT_STORE)->getStore();

        if (! $store instanceof DatabaseStore) {
            throw CacheTagsException::nonDatabaseStore($store::class);
        }

        return $store;
    }

    /**
     * Échappe les métacaractères LIKE MySQL (`%`, `_`, `\`) pour garantir
     * que le préfixe fourni n'est pas interprété comme un wildcard SQL.
     */
    private function escapeLikeLiteral(string $value): string
    {
        return addcslashes($value, '\\%_');
    }
}
