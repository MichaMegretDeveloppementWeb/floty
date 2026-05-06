<?php

declare(strict_types=1);

namespace App\Repositories\User\Billing;

use App\Contracts\Repositories\User\Billing\BillingSettingsWriteRepositoryInterface;
use App\Data\User\Billing\BillingSettingsData;
use App\Models\BillingSettings;

final class BillingSettingsWriteRepository implements BillingSettingsWriteRepositoryInterface
{
    public function update(BillingSettingsData $data): BillingSettings
    {
        $settings = BillingSettings::singleton();

        $settings->fill([
            'name' => $data->name,
            'address_line_1' => $data->addressLine1,
            'address_line_2' => $data->addressLine2,
            'postal_code' => $data->postalCode,
            'city' => $data->city,
            'siren' => $data->siren,
            'contact_email' => $data->contactEmail,
        ]);
        $settings->save();

        return $settings;
    }
}
