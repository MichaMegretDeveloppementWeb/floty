<?php

declare(strict_types=1);

namespace App\Repositories\User\Billing;

use App\Contracts\Repositories\User\Billing\BillingSettingsReadRepositoryInterface;
use App\Models\BillingSettings;

final class BillingSettingsReadRepository implements BillingSettingsReadRepositoryInterface
{
    public function get(): BillingSettings
    {
        return BillingSettings::singleton();
    }
}
