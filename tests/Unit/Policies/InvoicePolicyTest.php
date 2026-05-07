<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Invoice;
use App\Models\User;
use App\Policies\InvoicePolicy;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Vérifie le comportement V1 mono-tenant : toutes les abilities retournent
 * `true`. Ces tests servent de filet de sécurité contre une régression
 * involontaire si V2 modifie la logique sans mettre à jour les tests.
 */
final class InvoicePolicyTest extends TestCase
{
    #[Test]
    public function view_any_retourne_true_v1(): void
    {
        $policy = new InvoicePolicy;
        $user = new User;

        self::assertTrue($policy->viewAny($user));
    }

    #[Test]
    public function view_retourne_true_v1(): void
    {
        $policy = new InvoicePolicy;

        self::assertTrue($policy->view(new User, new Invoice));
    }

    #[Test]
    public function create_retourne_true_v1(): void
    {
        $policy = new InvoicePolicy;

        self::assertTrue($policy->create(new User));
    }

    #[Test]
    public function update_retourne_true_v1(): void
    {
        $policy = new InvoicePolicy;

        self::assertTrue($policy->update(new User, new Invoice));
    }

    #[Test]
    public function delete_retourne_true_v1(): void
    {
        $policy = new InvoicePolicy;

        self::assertTrue($policy->delete(new User, new Invoice));
    }
}
