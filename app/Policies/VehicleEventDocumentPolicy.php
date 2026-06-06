<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\VehicleEventDocument;

/**
 * VehicleEvent document policy. V1 stub returning `true`; multi-tenant scoping ships in V2 (ADR-0011 § 7).
 */
final class VehicleEventDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, VehicleEventDocument $document): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function delete(User $user, VehicleEventDocument $document): bool
    {
        return true;
    }
}
