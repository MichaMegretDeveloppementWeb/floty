<?php

declare(strict_types=1);

namespace App\Exceptions\Auth;

use App\Exceptions\BaseAppException;

/**
 * Password reset failure (invalid or expired token).
 *
 * User message is intentionally ambiguous to avoid leaking token state to attackers.
 */
final class InvalidResetTokenException extends BaseAppException
{
    public static function make(): self
    {
        return new self(
            technicalMessage: 'Password reset failed: invalid or expired token.',
            userMessage: 'Le lien de réinitialisation est invalide ou a expiré. Veuillez en demander un nouveau.',
        );
    }
}
