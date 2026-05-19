<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Company;
use App\Models\User;
use App\Policies\CompanyPolicy;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Vérifie le comportement V1 mono-tenant : toutes les abilities retournent
 * `true`. Filet de sécurité contre une régression involontaire si V2
 * modifie la logique sans mettre à jour les tests.
 *
 * Cf. plan-remédiation Vague 1 Lot 1 D7 (F-30-003).
 */
final class CompanyPolicyTest extends TestCase
{
    #[Test]
    public function toutes_les_abilities_retournent_true_v1(): void
    {
        $policy = new CompanyPolicy;
        $user = new User;
        $company = new Company;

        $this->assertTrue($policy->viewAny($user));
        $this->assertTrue($policy->view($user, $company));
        $this->assertTrue($policy->create($user));
        $this->assertTrue($policy->update($user, $company));
        $this->assertTrue($policy->delete($user, $company));
    }
}
