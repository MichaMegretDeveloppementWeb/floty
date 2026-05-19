<?php

declare(strict_types=1);

namespace App\Exceptions\Cache;

use App\Exceptions\BaseAppException;

/**
 * Configuration or usage error in the emulated cache tags manager (Floty uses the `database` driver, ADR-0008).
 */
final class CacheTagsException extends BaseAppException
{
    public static function keyRequiresAtLeastOneSegment(): self
    {
        return new self(
            technicalMessage: 'CacheTagsManager::key() requires at least one segment.',
            userMessage: "Erreur interne lors de la composition d'une clé de cache. Veuillez contacter le support.",
        );
    }

    public static function nonDatabaseStore(string $actualStoreClass): self
    {
        return new self(
            technicalMessage: "CacheTagsManager requires the `database` cache store; got {$actualStoreClass}.",
            userMessage: 'Erreur de configuration du cache applicatif. Veuillez contacter le support.',
        );
    }
}
