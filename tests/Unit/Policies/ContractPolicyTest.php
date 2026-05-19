<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Contract;
use App\Models\User;
use App\Policies\ContractPolicy;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Vérifie le comportement V1 mono-tenant : toutes les abilities retournent
 * `true`. Filet de sécurité contre une régression involontaire si V2
 * modifie la logique sans mettre à jour les tests.
 *
 * Cf. plan-remédiation Vague 1 Lot 1 D6 (F-12-001).
 */
final class ContractPolicyTest extends TestCase
{
    #[Test]
    public function view_any_retourne_true_v1(): void
    {
        $policy = new ContractPolicy;
        $user = new User;

        $this->assertTrue($policy->viewAny($user));
    }

    #[Test]
    public function view_retourne_true_v1(): void
    {
        $policy = new ContractPolicy;
        $user = new User;
        $contract = new Contract;

        $this->assertTrue($policy->view($user, $contract));
    }

    #[Test]
    public function create_retourne_true_v1(): void
    {
        $policy = new ContractPolicy;
        $user = new User;

        $this->assertTrue($policy->create($user));
    }

    #[Test]
    public function update_retourne_true_v1(): void
    {
        $policy = new ContractPolicy;
        $user = new User;
        $contract = new Contract;

        $this->assertTrue($policy->update($user, $contract));
    }

    #[Test]
    public function delete_retourne_true_v1(): void
    {
        $policy = new ContractPolicy;
        $user = new User;
        $contract = new Contract;

        $this->assertTrue($policy->delete($user, $contract));
    }
}
