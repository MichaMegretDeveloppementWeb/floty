<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Billing;

use App\Data\User\Billing\BillingSettingsData;
use App\Models\BillingSettings;

/**
 * Invoice issuer write (singleton).
 */
interface BillingSettingsWriteRepositoryInterface
{
    /**
     * Updates the singleton row with the DTO values. Creates the row
     * on first call if it did not exist yet.
     */
    public function update(BillingSettingsData $data): BillingSettings;
}
