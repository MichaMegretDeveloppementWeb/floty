<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Models\Vehicle;
use App\Policies\VehiclePolicy;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Cf. plan-remédiation Vague 1 Lot 1 D7 (F-30-003).
 */
final class VehiclePolicyTest extends TestCase
{
    #[Test]
    public function toutes_les_abilities_retournent_true_v1(): void
    {
        $policy = new VehiclePolicy;
        $user = new User;
        $vehicle = new Vehicle;

        $this->assertTrue($policy->viewAny($user));
        $this->assertTrue($policy->view($user, $vehicle));
        $this->assertTrue($policy->create($user));
        $this->assertTrue($policy->update($user, $vehicle));
        $this->assertTrue($policy->delete($user, $vehicle));
    }
}
