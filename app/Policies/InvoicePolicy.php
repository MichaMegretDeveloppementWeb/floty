<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

/**
 * Policy facture : stub V1 mono-tenant (Phase 14.O).
 *
 * Toutes les méthodes retournent `true` en V1 : l'application n'expose
 * qu'un périmètre utilisateur global, sans scoping par société. Les
 * controllers appellent déjà `Gate::authorize(...)` pour que la logique
 * multi-tenant V2 puisse s'ajouter ici sans refactor des controllers.
 *
 * Migration V2 attendue : scoper l'accès par appartenance à la société
 * de location (qui édite les factures) ou par entreprise utilisatrice
 * destinataire selon le rôle de l'utilisateur connecté.
 */
final class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return true;
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return true;
    }
}
