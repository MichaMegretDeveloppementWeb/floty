<?php

declare(strict_types=1);

namespace App\Exceptions\Cache;

use App\Exceptions\BaseAppException;

/**
 * Erreur de configuration ou d'utilisation du gestionnaire de tags de
 * cache émulés (Floty utilise le driver `database` en V1, cf. ADR-0008).
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
