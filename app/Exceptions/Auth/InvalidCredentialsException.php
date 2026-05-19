<?php

declare(strict_types=1);

namespace App\Exceptions\Auth;

use App\Exceptions\BaseAppException;

/**
 * Authentication failure (unknown email or wrong password).
 *
 * The user message is intentionally ambiguous to avoid account enumeration (OWASP, ADR-0011).
 */
final class InvalidCredentialsException extends BaseAppException
{
    public static function make(): self
    {
        return new self(
            technicalMessage: 'Login attempt failed: invalid email or password.',
            userMessage: 'Identifiants invalides.',
        );
    }
}
