<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Models\VehicleFiscalCharacteristics;
use App\Policies\VehicleFiscalCharacteristicsPolicy;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Cf. plan-remédiation Vague 1 Lot 1 D7 (F-30-003).
 */
final class VehicleFiscalCharacteristicsPolicyTest extends TestCase
{
    #[Test]
    public function toutes_les_abilities_retournent_true_v1(): void
    {
        $policy = new VehicleFiscalCharacteristicsPolicy;
        $user = new User;
        $vfc = new VehicleFiscalCharacteristics;

        $this->assertTrue($policy->viewAny($user));
        $this->assertTrue($policy->view($user, $vfc));
        $this->assertTrue($policy->create($user));
        $this->assertTrue($policy->update($user, $vfc));
        $this->assertTrue($policy->delete($user, $vfc));
    }
}
