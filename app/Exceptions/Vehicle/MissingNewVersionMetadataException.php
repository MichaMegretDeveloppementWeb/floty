<?php

declare(strict_types=1);

namespace App\Exceptions\Vehicle;

use App\Exceptions\BaseAppException;

/**
 * Vehicle update has fiscal changes but lacks the `effective_from` / `change_reason` metadata
 * required to materialize a new history version. Backend safety net.
 */
final class MissingNewVersionMetadataException extends BaseAppException
{
    public static function make(): self
    {
        return new self(
            technicalMessage: 'Vehicle update has fiscal changes but lacks effectiveFrom or changeReason.',
            userMessage: 'Modification fiscale détectée : la date d\'effet et le motif sont obligatoires pour créer une nouvelle version d\'historique.',
        );
    }
}
