<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ControlExecutionDocument;
use App\Models\User;

/**
 * Control execution documents policy. V1 stub returning `true`; multi-tenant
 * scoping ships in V2 (ADR-0011 § 7).
 */
final class ControlExecutionDocumentPolicy
{
    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, ControlExecutionDocument $document): bool
    {
        return true;
    }
}
