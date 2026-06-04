<?php

declare(strict_types=1);

namespace App\Repositories\User\Control;

use App\Contracts\Repositories\User\Control\VehicleControlOverrideWriteRepositoryInterface;
use App\Data\User\Control\ControlRecipientData;
use App\Enums\Control\RecipientLevel;
use App\Enums\Control\RecipientOperation;
use App\Models\VehicleControlOverride;
use App\Support\Control\RecipientEmail;

final class VehicleControlOverrideWriteRepository implements VehicleControlOverrideWriteRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): VehicleControlOverride
    {
        return VehicleControlOverride::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(VehicleControlOverride $override, array $attributes): VehicleControlOverride
    {
        $override->fill($attributes);
        $override->save();

        return $override;
    }

    public function softDelete(VehicleControlOverride $override): void
    {
        $override->delete();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function upsertForVehicleDefinition(int $vehicleId, int $definitionId, array $attributes): VehicleControlOverride
    {
        // withTrashed so a previously "reset to global" (soft-deleted) row is
        // reused rather than colliding with the unique index.
        $override = VehicleControlOverride::withTrashed()->firstOrNew([
            'vehicle_id' => $vehicleId,
            'control_definition_id' => $definitionId,
        ]);

        $override->fill($attributes);
        $override->deleted_at = null;
        $override->save();

        return $override;
    }

    /**
     * @param  array<int, ControlRecipientData>  $ownRecipients
     * @param  array<int, string>  $excludedEmails
     */
    public function syncRecipients(VehicleControlOverride $override, array $ownRecipients, array $excludedEmails): void
    {
        $override->recipientDeltas()->delete();

        $seen = [];

        foreach ($ownRecipients as $recipient) {
            $email = RecipientEmail::normalize($recipient->email);

            if (isset($seen[$email])) {
                continue;
            }

            $seen[$email] = true;

            $override->recipientDeltas()->create([
                'level' => RecipientLevel::Vehicle,
                'operation' => RecipientOperation::Include,
                'email' => $email,
                'name' => $recipient->name,
            ]);
        }

        foreach ($excludedEmails as $rawEmail) {
            $email = RecipientEmail::normalize($rawEmail);

            if (isset($seen[$email])) {
                continue;
            }

            $seen[$email] = true;

            $override->recipientDeltas()->create([
                'level' => RecipientLevel::Vehicle,
                'operation' => RecipientOperation::Exclude,
                'email' => $email,
                'name' => null,
            ]);
        }
    }
}
