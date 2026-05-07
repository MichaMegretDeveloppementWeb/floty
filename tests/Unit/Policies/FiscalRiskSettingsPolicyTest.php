<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\FiscalRiskSettings;
use App\Models\User;
use App\Policies\FiscalRiskSettingsPolicy;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FiscalRiskSettingsPolicyTest extends TestCase
{
    #[Test]
    public function view_retourne_true_v1(): void
    {
        $policy = new FiscalRiskSettingsPolicy;

        self::assertTrue($policy->view(new User, new FiscalRiskSettings));
    }

    #[Test]
    public function update_retourne_true_v1(): void
    {
        $policy = new FiscalRiskSettingsPolicy;

        self::assertTrue($policy->update(new User, new FiscalRiskSettings));
    }
}
