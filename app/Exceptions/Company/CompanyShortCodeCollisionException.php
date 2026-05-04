<?php

declare(strict_types=1);

namespace App\Exceptions\Company;

use App\Exceptions\BaseAppException;

/**
 * Levée par CreateCompanyAction quand le code court auto-généré entre en
 * collision avec une entreprise existante (UNIQUE constraint sur
 * `companies.short_code`). Le code court n'étant plus saisi par
 * l'utilisateur (auto-généré depuis le nom légal), la collision se résout
 * en lui demandant de reformuler le nom de la nouvelle entreprise.
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
