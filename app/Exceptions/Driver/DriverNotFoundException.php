<?php

declare(strict_types=1);

namespace App\Exceptions\Driver;

use App\Exceptions\BaseAppException;

final class DriverNotFoundException extends BaseAppException
{
    public static function byId(int $driverId): self
    {
        return new self(
            sprintf('Driver %d not found.', $driverId),
            'Conducteur introuvable.',
        );
    }
}
