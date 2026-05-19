<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\FiscalRiskSettings;

use App\Models\FiscalRiskSettings;

/**
 * Reads of the fiscal risk detection thresholds (singleton per
 * ADR-0015 § D7 rev. 1.1).
 */
interface FiscalRiskSettingsReadRepositoryInterface
{
    /**
     * Returns the unique row (auto-creation when the table is empty).
     */
    public function get(): FiscalRiskSettings;
}
