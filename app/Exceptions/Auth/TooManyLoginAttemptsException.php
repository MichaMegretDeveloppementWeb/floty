<?php

declare(strict_types=1);

namespace App\Exceptions\Auth;

use App\Exceptions\BaseAppException;

/**
 * Rate-limit déclenché sur les tentatives de connexion (ADR-0011).
 *
 * Deux portées distinctes :
 *   - {@see SCOPE_EMAIL} : 5 tentatives / 15 min sur le couple email+IP
 *     (anti bruteforce ciblé d'un compte précis)
 *   - {@see SCOPE_IP} : 50 tentatives / 15 min sur l'IP seule
 *     (anti attaques distribuées multi-comptes depuis une même origine)
 *
 * `retryAfterSeconds` est le délai exact retourné par RateLimiter, exposé
 * à l'utilisateur dans le message pour qu'il sache quand réessayer.
 *
 * Référence : implementation-rules/gestion-erreurs.md.
 */
final class TooManyLoginAttemptsException extends BaseAppException
{
    public const string SCOPE_EMAIL = 'email';

    public const string SCOPE_IP = 'ip';

    private function __construct(
        string $technicalMessage,
        string $userMessage,
        public readonly int $retryAfterSeconds,
        public readonly string $scope,
    ) {
        parent::__construct($technicalMessage, $userMessage);
    }

    public static function forEmail(string $email, int $retryAfterSeconds): self
    {
        return new self(
            technicalMessage: "Login rate-limit reached for email '{$email}' - wait {$retryAfterSeconds}s.",
            userMessage: sprintf(
                'Trop de tentatives. Réessayez dans %d secondes.',
                $retryAfterSeconds,
            ),
            retryAfterSeconds: $retryAfterSeconds,
            scope: self::SCOPE_EMAIL,
        );
    }

    public static function forIp(string $ip, int $retryAfterSeconds): self
    {
        return new self(
            technicalMessage: "Login rate-limit reached for IP '{$ip}' - wait {$retryAfterSeconds}s.",
            userMessage: sprintf(
                'Trop de tentatives depuis cette IP. Réessayez dans %d secondes.',
                $retryAfterSeconds,
            ),
            retryAfterSeconds: $retryAfterSeconds,
            scope: self::SCOPE_IP,
        );
    }
}
