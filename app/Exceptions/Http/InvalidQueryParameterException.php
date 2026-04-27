<?php

declare(strict_types=1);

namespace App\Exceptions\Http;

use App\Exceptions\BaseAppException;

/**
 * Paramètre de requête HTTP manquant ou hors plage. Remplace les
 * `abort(400, ...)` directs dans les controllers qui consommaient des
 * query strings.
 *
 * Toujours renvoyée par les controllers ; le handler global
 * (bootstrap/app.php) la convertit en JSON 422 pour les requêtes Ajax
 * (`useApi`) ou en flash + back pour les visites Inertia.
 */
final class InvalidQueryParameterException extends BaseAppException
{
    public static function missing(string $param): self
    {
        return new self(
            technicalMessage: "Missing required query parameter '{$param}'.",
            userMessage: "Paramètre requis manquant ({$param}). Rechargez la page ou contactez le support si le problème persiste.",
        );
    }

    public static function outOfRange(string $param, int|string $value, string $expected): self
    {
        return new self(
            technicalMessage: "Query parameter '{$param}' value '{$value}' is out of range; expected {$expected}.",
            userMessage: "Valeur invalide pour le paramètre {$param}. Rechargez la page ou contactez le support.",
        );
    }
}
