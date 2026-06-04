<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ControlDefinition;
use App\Models\User;

/**
 * Global control definitions policy. V1 stub returning `true`; multi-tenant
 * scoping ships in V2 (ADR-0011 § 7), mirroring the other V1 policies.
 */
final class ControlDefinitionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ControlDefinition $definition): bool
    {
        return true;
    }

    public function delete(User $user, ControlDefinition $definition): bool
    {
        return true;
    }
}
