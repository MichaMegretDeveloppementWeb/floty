<?php

declare(strict_types=1);

namespace App\Exceptions\VehicleRegistryLookup;

use App\Exceptions\BaseAppException;

final class RegistryLookupRateLimitedException extends BaseAppException
{
    /**
     * Build the exception when the upstream provider returns a rate-limit response.
     */
    public static function fromProvider(string $provider, ?int $retryAfterSeconds = null): self
    {
        $userHint = $retryAfterSeconds !== null
            ? "Patientez {$retryAfterSeconds} secondes avant de réessayer."
            : 'Patientez quelques instants avant de réessayer.';

        return new self(
            technicalMessage: "Vehicle registry provider [{$provider}] rate limit reached.".
                ($retryAfterSeconds !== null ? " Retry-After: {$retryAfterSeconds}s." : ''),
            userMessage: "Le quota de requêtes du service est atteint. {$userHint}",
        );
    }
}
