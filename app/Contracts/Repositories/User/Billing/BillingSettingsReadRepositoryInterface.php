<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Billing;

use App\Models\BillingSettings;

/**
 * Invoice issuer read (singleton).
 */
interface BillingSettingsReadRepositoryInterface
{
    /**
     * Returns the unique row (auto-creation when the table is empty).
     */
    public function get(): BillingSettings;
}
