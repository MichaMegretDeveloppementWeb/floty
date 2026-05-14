<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Unavailability;
use App\Models\User;
use App\Policies\UnavailabilityPolicy;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Cf. plan-remédiation Vague 1 Lot 1 D7 (F-30-003).
 */
final class UnavailabilityPolicyTest extends TestCase
{
    #[Test]
    public function toutes_les_abilities_retournent_true_v1(): void
    {
        $policy = new UnavailabilityPolicy;
        $user = new User;
        $unavailability = new Unavailability;

        $this->assertTrue($policy->viewAny($user));
        $this->assertTrue($policy->view($user, $unavailability));
        $this->assertTrue($policy->create($user));
        $this->assertTrue($policy->update($user, $unavailability));
        $this->assertTrue($policy->delete($user, $unavailability));
    }
}
