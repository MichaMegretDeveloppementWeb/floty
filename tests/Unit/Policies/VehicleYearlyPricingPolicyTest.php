<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Models\VehicleYearlyPricing;
use App\Policies\VehicleYearlyPricingPolicy;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class VehicleYearlyPricingPolicyTest extends TestCase
{
    #[Test]
    public function view_any_retourne_true_v1(): void
    {
        $policy = new VehicleYearlyPricingPolicy;

        self::assertTrue($policy->viewAny(new User));
    }

    #[Test]
    public function view_retourne_true_v1(): void
    {
        $policy = new VehicleYearlyPricingPolicy;

        self::assertTrue($policy->view(new User, new VehicleYearlyPricing));
    }

    #[Test]
    public function create_retourne_true_v1(): void
    {
        $policy = new VehicleYearlyPricingPolicy;

        self::assertTrue($policy->create(new User));
    }

    #[Test]
    public function update_retourne_true_v1(): void
    {
        $policy = new VehicleYearlyPricingPolicy;

        self::assertTrue($policy->update(new User, new VehicleYearlyPricing));
    }

    #[Test]
    public function delete_retourne_true_v1(): void
    {
        $policy = new VehicleYearlyPricingPolicy;

        self::assertTrue($policy->delete(new User, new VehicleYearlyPricing));
    }
}
