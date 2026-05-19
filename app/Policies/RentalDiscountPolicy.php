<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\RentalDiscount;
use App\Models\User;

/**
 * Rental discount policy. V1 stub returning `true`; multi-tenant scoping ships in V2 (ADR-0011 § 7).
 */
final class RentalDiscountPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, RentalDiscount $discount): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, RentalDiscount $discount): bool
    {
        return true;
    }

    public function delete(User $user, RentalDiscount $discount): bool
    {
        return true;
    }
}
