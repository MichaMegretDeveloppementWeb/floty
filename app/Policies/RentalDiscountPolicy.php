<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\RentalDiscount;
use App\Models\User;

/**
 * Policy stub V1 · tous les gestionnaires de flotte authentifiés ont
 * accès complet aux réductions commerciales (lecture + création +
 * édition + suppression). Aligné avec le pattern des policies sœurs
 * (`DriverPolicy`, `CompanyPolicy`).
 *
 * V2 (post-livraison) pourra introduire des ACL plus fines (lecture
 * seule pour certains rôles, séparation create/edit) sans casser
 * l'API publique de la classe.
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
