<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Driver;
use App\Models\User;
use App\Policies\DriverPolicy;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Cf. plan-remédiation Vague 1 Lot 1 D7 (F-30-003).
 */
final class DriverPolicyTest extends TestCase
{
    #[Test]
    public function toutes_les_abilities_retournent_true_v1(): void
    {
        $policy = new DriverPolicy;
        $user = new User;
        $driver = new Driver;

        $this->assertTrue($policy->viewAny($user));
        $this->assertTrue($policy->view($user, $driver));
        $this->assertTrue($policy->create($user));
        $this->assertTrue($policy->update($user, $driver));
        $this->assertTrue($policy->delete($user, $driver));
    }
}
