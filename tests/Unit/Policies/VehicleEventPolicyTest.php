<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\VehicleEvent;
use App\Models\User;
use App\Policies\VehicleEventPolicy;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Cf. plan-remédiation Vague 1 Lot 1 D7 (F-30-003).
 */
final class VehicleEventPolicyTest extends TestCase
{
    #[Test]
    public function toutes_les_abilities_retournent_true_v1(): void
    {
        $policy = new VehicleEventPolicy;
        $user = new User;
        $vehicleEvent = new VehicleEvent;

        $this->assertTrue($policy->viewAny($user));
        $this->assertTrue($policy->view($user, $vehicleEvent));
        $this->assertTrue($policy->create($user));
        $this->assertTrue($policy->update($user, $vehicleEvent));
        $this->assertTrue($policy->delete($user, $vehicleEvent));
    }
}
