<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Policies\PlanningPolicy;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Cf. plan-remédiation Vague 1 Lot 1 D7 (F-30-003).
 */
final class PlanningPolicyTest extends TestCase
{
    #[Test]
    public function view_any_retourne_true_v1(): void
    {
        $policy = new PlanningPolicy;
        $user = new User;

        $this->assertTrue($policy->viewAny($user));
    }
}
