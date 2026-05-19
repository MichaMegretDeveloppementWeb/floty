<?php

declare(strict_types=1);

namespace App\Exceptions\Auth;

use App\Exceptions\BaseAppException;

/**
 * Change-password failure: provided current password does not match the stored hash.
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
