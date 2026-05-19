<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\FiscalDeclaration;
use App\Models\User;

/**
 * Fiscal declaration policy. V1 stub returning `true`; multi-tenant scoping ships in V2 (ADR-0011 § 7).
 *
 * No `delete` ability: a `generated` declaration is immutable (ADR-0008 + ADR-0015 § D8 rev. 1.1) and only soft-deletable internally.
 */
final class FiscalDeclarationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, FiscalDeclaration $declaration): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, FiscalDeclaration $declaration): bool
    {
        return true;
    }
}
