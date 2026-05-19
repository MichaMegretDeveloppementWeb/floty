<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\FiscalRiskSettings;

use App\Data\User\FiscalRiskSettings\FiscalRiskSettingsData;
use App\Models\FiscalRiskSettings;

/**
 * Writes of the fiscal risk detection thresholds (singleton per
 * ADR-0015 § D7 rev. 1.1).
 */
interface FiscalRiskSettingsWriteRepositoryInterface
{
    /**
     * Updates the singleton row with the DTO values. Creates the row
     * on first call if it did not exist yet.
     */
    public function update(FiscalRiskSettingsData $data): FiscalRiskSettings;
}
