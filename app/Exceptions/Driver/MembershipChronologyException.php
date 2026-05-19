<?php

declare(strict_types=1);

namespace App\Exceptions\Driver;

use App\Exceptions\BaseAppException;

/**
 * Invalid membership chronology: `joined_at > left_at`.
 */
final class MembershipChronologyException extends BaseAppException
{
    public static function joinedAtAfterLeftAt(string $joinedAt, string $leftAt): self
    {
        return new self(
            sprintf('joined_at (%s) must be <= left_at (%s).', $joinedAt, $leftAt),
            "La date d'entrée doit être antérieure ou égale à la date de sortie.",
        );
    }
}
