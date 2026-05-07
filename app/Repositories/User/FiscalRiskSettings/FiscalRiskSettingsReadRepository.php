<?php

declare(strict_types=1);

namespace App\Repositories\User\FiscalRiskSettings;

use App\Contracts\Repositories\User\FiscalRiskSettings\FiscalRiskSettingsReadRepositoryInterface;
use App\Models\FiscalRiskSettings;

final class FiscalRiskSettingsReadRepository implements FiscalRiskSettingsReadRepositoryInterface
{
    public function get(): FiscalRiskSettings
    {
        return FiscalRiskSettings::singleton();
    }
}
