<?php

declare(strict_types=1);

namespace App\Exceptions\VehicleRegistryLookup;

use App\Exceptions\BaseAppException;
use Throwable;

final class RegistryLookupTimeoutException extends BaseAppException
{
    /**
     * Build the exception when the provider exceeds the configured timeout.
     */
    public static function afterSeconds(int $seconds, ?Throwable $previous = null): self
    {
        return new self(
            technicalMessage: "Vehicle registry provider did not respond within {$seconds}s.",
            userMessage: 'Le service est lent à répondre. Réessayez dans un instant ou saisissez les informations manuellement.',
            previous: $previous,
        );
    }
}
