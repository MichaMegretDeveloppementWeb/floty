<?php

declare(strict_types=1);

namespace App\Exceptions\Http;

use App\Exceptions\BaseAppException;

/**
 * Missing or out-of-range HTTP query parameter.
 *
 * Converted to JSON 422 (Ajax) or flash + back (Inertia) by the global handler in `bootstrap/app.php`.
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
