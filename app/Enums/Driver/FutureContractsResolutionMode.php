<?php

declare(strict_types=1);

namespace App\Enums\Driver;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Resolution mode for upcoming contracts when a driver leaves a company.
 *
 * - `Replace`: per contract, pick a replacement driver active on that period.
 * - `Detach`: upcoming contracts have `driver_id` set to `NULL`.
 * - `None`: no upcoming contracts, or the user opted to do nothing.
 */
#[TypeScript]
enum FutureContractsResolutionMode: string
{
    case Replace = 'replace';
    case Detach = 'detach';
    case None = 'none';
}
