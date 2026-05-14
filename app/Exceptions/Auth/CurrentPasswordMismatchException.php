<?php

declare(strict_types=1);

namespace App\Exceptions\Auth;

use App\Exceptions\BaseAppException;

/**
 * Échec de changement de mot de passe · le mot de passe actuel fourni
 * ne correspond pas au password stocké pour l'utilisateur connecté.
 *
 * Comme pour les autres exceptions Auth · pas d'information détaillée
 * (un attaquant ayant volé une session pourrait sinon brute-forcer
 * le password actuel via ce formulaire).
 */
final class CurrentPasswordMismatchException extends BaseAppException
{
    public static function make(): self
    {
        return new self(
            technicalMessage: 'ChangePassword failed: provided current password does not match stored hash.',
            userMessage: 'Le mot de passe actuel est incorrect.',
        );
    }
}
