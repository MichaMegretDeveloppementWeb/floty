<?php

declare(strict_types=1);

namespace App\Exceptions\Auth;

use App\Exceptions\BaseAppException;

/**
 * Échec d'authentification — email inconnu ou mot de passe incorrect.
 *
 * Le message utilisateur est volontairement ambigu (ne distingue pas
 * « email inconnu » de « mot de passe faux ») pour ne pas divulguer
 * l'existence d'un compte (OWASP, ADR-0011).
 *
 * Référence : implementation-rules/gestion-erreurs.md.
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
