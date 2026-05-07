<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\FiscalDeclaration;
use App\Models\User;

/**
 * Policy déclaration fiscale : stub V1 mono-tenant (Phase 11 D4).
 *
 * Toutes les méthodes retournent `true` en V1. Cohérent avec
 * {@see InvoicePolicy} et {@see BillingSettingsPolicy} : la logique
 * multi-tenant V2 s'ajoutera ici sans toucher aux controllers qui
 * appellent déjà `Gate::authorize(...)`.
 *
 * Pas d'ability `delete` exposée : conformément à la doctrine
 * immuabilité fiscale (ADR-0008 + ADR-0015 § D8 rev. 1.1), une
 * déclaration `generated` ne peut JAMAIS être supprimée hors-app
 * (soft-delete uniquement, jamais déclenché par un user direct).
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
