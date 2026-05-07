<?php

declare(strict_types=1);

namespace App\Repositories\User\FiscalRiskSettings;

use App\Contracts\Repositories\User\FiscalRiskSettings\FiscalRiskSettingsWriteRepositoryInterface;
use App\Data\User\FiscalRiskSettings\FiscalRiskSettingsData;
use App\Models\FiscalRiskSettings;

final class FiscalRiskSettingsWriteRepository implements FiscalRiskSettingsWriteRepositoryInterface
{
    public function update(FiscalRiskSettingsData $data): FiscalRiskSettings
    {
        $settings = FiscalRiskSettings::singleton();

        $settings->fill([
            'max_interval' => $data->maxInterval,
            'threshold_low' => $data->thresholdLow,
            'threshold_high' => $data->thresholdHigh,
            'count_high' => $data->countHigh,
            'lld_breaks_chain' => $data->lldBreaksChain,
        ]);
        $settings->save();

        return $settings;
    }
}
