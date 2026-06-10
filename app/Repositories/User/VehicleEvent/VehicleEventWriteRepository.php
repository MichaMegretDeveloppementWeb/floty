<?php

declare(strict_types=1);

namespace App\Repositories\User\VehicleEvent;

use App\Contracts\Repositories\User\VehicleEvent\VehicleEventWriteRepositoryInterface;
use App\Enums\VehicleEvent\VehicleEventSystemKind;
use App\Models\VehicleEvent;
use Illuminate\Support\Facades\DB;

final class VehicleEventWriteRepository implements VehicleEventWriteRepositoryInterface
{
    public function create(array $attributes, array $categories = [], array $details = []): VehicleEvent
    {
        return DB::transaction(function () use ($attributes, $categories, $details): VehicleEvent {
            $vehicleEvent = VehicleEvent::create($attributes);
            $this->replaceCategories($vehicleEvent, $categories);
            $this->replaceDetails($vehicleEvent, $details);

            return $vehicleEvent;
        });
    }

    public function update(int $id, array $attributes, array $categories = [], array $details = []): VehicleEvent
    {
        return DB::transaction(function () use ($id, $attributes, $categories, $details): VehicleEvent {
            $vehicleEvent = VehicleEvent::query()->findOrFail($id);
            $vehicleEvent->update($attributes);
            $this->replaceCategories($vehicleEvent, $categories);
            $this->replaceDetails($vehicleEvent, $details);

            return $vehicleEvent->fresh();
        });
    }

    public function softDelete(int $id): void
    {
        VehicleEvent::query()->findOrFail($id)->delete();
    }

    public function deleteSystemEventsForVehicle(int $vehicleId, VehicleEventSystemKind $kind): void
    {
        VehicleEvent::query()
            ->where('vehicle_id', $vehicleId)
            ->where('system_kind', $kind)
            ->delete();
    }

    /**
     * Replace the event's categories with the given (already composed /
     * deduped / capped) list, in order.
     *
     * @param  list<string>  $categories
     */
    private function replaceCategories(VehicleEvent $vehicleEvent, array $categories): void
    {
        $vehicleEvent->categories()->delete();

        foreach ($categories as $category) {
            $vehicleEvent->categories()->create(['category' => $category]);
        }
    }

    /**
     * Replaces the event's detail rows with the composed list, in order.
     *
     * @param  list<string>  $details
     */
    private function replaceDetails(VehicleEvent $vehicleEvent, array $details): void
    {
        $vehicleEvent->details()->delete();

        foreach ($details as $detail) {
            $vehicleEvent->details()->create(['detail' => $detail]);
        }
    }
}
