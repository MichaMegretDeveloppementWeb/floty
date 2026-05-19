<?php

declare(strict_types=1);

namespace App\Exceptions\Company;

use App\Exceptions\BaseAppException;

/**
 * Auto-generated short code collides with an existing company (UNIQUE constraint on `companies.short_code`).
 * Resolution: ask the user to reformulate the legal name to produce a different code.
 */
final class CompanyShortCodeCollisionException extends BaseAppException
{
    public static function forCode(string $shortCode): self
    {
        return new self(
            sprintf('Generated short code "%s" already exists.', $shortCode),
            sprintf(
                'Le code court généré (%s) est déjà utilisé par une autre entreprise. '
                .'Reformulez la raison sociale pour générer un code différent.',
                $shortCode,
            ),
        );
    }
}
