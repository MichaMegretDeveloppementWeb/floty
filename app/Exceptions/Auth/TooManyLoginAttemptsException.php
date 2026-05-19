<?php

declare(strict_types=1);

namespace App\Exceptions\Auth;

use App\Exceptions\BaseAppException;

/**
 * Login rate-limit hit (ADR-0011).
 *
 * Two scopes: {@see SCOPE_EMAIL} (5 attempts / 15 min on email+IP) and {@see SCOPE_IP}
 * (50 attempts / 15 min on IP alone). `retryAfterSeconds` is the exact RateLimiter delay.
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
