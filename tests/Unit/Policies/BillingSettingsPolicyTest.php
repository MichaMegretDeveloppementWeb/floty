<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\BillingSettings;
use App\Models\User;
use App\Policies\BillingSettingsPolicy;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BillingSettingsPolicyTest extends TestCase
{
    #[Test]
    public function view_retourne_true_v1(): void
    {
        $policy = new BillingSettingsPolicy;

        self::assertTrue($policy->view(new User, new BillingSettings));
    }

    #[Test]
    public function update_retourne_true_v1(): void
    {
        $policy = new BillingSettingsPolicy;

        self::assertTrue($policy->update(new User, new BillingSettings));
    }
}
