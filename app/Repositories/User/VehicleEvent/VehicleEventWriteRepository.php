<?php

declare(strict_types=1);

namespace App\Repositories\User\VehicleEvent;

use App\Contracts\Repositories\User\VehicleEvent\VehicleEventWriteRepositoryInterface;
use App\Models\VehicleEvent;
use Illuminate\Support\Facades\DB;

final class VehicleEventWriteRepository implements VehicleEventWriteRepositoryInterface
{
    public function create(array $attributes, array $categories = []): VehicleEvent
    {
        return DB::transaction(function () use ($attributes, $categories): VehicleEvent {
            $vehicleEvent = VehicleEvent::create($attributes);
            $this->replaceCategories($vehicleEvent, $categories);

            return $vehicleEvent;
        });
    }

    public function update(int $id, array $attributes, array $categories = []): VehicleEvent
    {
        return DB::transaction(function () use ($id, $attributes, $categories): VehicleEvent {
            $vehicleEvent = VehicleEvent::query()->findOrFail($id);
            $vehicleEvent->update($attributes);
            $this->replaceCategories($vehicleEvent, $categories);

            return $vehicleEvent->fresh();
        });
    }

    public function softDelete(int $id): void
    {
        VehicleEvent::query()->findOrFail($id)->delete();
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
}
