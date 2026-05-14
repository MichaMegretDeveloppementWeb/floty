<?php

declare(strict_types=1);

namespace App\Exceptions\Auth;

use App\Exceptions\BaseAppException;

/**
 * Échec de réinitialisation · token invalide ou expiré.
 *
 * Le message utilisateur est volontairement ambigu (ne distingue pas
 * « token expiré » de « token jamais émis ») pour ne pas divulguer
 * d'information utile à un attaquant. Cohérent avec
 * {@see InvalidCredentialsException} (anti email enumeration).
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
