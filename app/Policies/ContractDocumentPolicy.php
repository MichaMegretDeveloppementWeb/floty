<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ContractDocument;
use App\Models\User;

/**
 * Contract document policy. V1 stub returning `true`; multi-tenant scoping ships in V2 (ADR-0011 § 7).
 */
final class ContractDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ContractDocument $document): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function delete(User $user, ContractDocument $document): bool
    {
        return true;
    }
}
